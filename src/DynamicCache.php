<?php

namespace TractorCow\DynamicCache;

use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Deprecation;
use SilverStripe\ORM\DB;
use SilverStripe\Security\BasicAuth;
use SilverStripe\Security\Member;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Versioned\Versioned;

/**
 * Handles on the fly caching of pages
 *
 * @author Damian Mooyman
 * @package dynamiccache
 */
class DynamicCache implements Flushable
{

    use Extensible;
    use Injectable;
    use Configurable;

    public static function flush()
    {
        self::inst()->clear();
    }

    /**
     * Shortcut for handling configuration parameters
     */
    public function __call($name, $arguments)
    {
        if (preg_match('/^(?<op>(get)|(set))_(?<arg>.+)$/', $name, $matches)) {
            $field = $matches['arg'];
            Deprecation::notice('3.1', "Call DynamicCache::config()->$field directly");
            if ($matches['op'] === 'set') {
                return DynamicCache::config()->$field = $arguments[0];
            } else {
                return DynamicCache::config()->$field;
            }
        }
        return parent::__call($name, $arguments);
    }

    /**
     * Instance of DynamicCache
     *
     * @var DynamicCache
     */
    protected static $instance;

    /**
     * Return the current cache instance
     *
     * @return DynamicCache
     */
    public static function inst()
    {
        if (!self::$instance) {
            self::$instance = DynamicCache::create();
        }
        return self::$instance;
    }

    /**
     * @param HTTPRequest $request
     * @return bool|string
     */
    private static function getUrl(HTTPRequest $request) {
        $url = $request->getURL(true);

        // Remove base folders from the URL if webroot is hosted in a subfolder
        if (strlen($url) && strlen(BASE_URL)) {
            if (substr(strtolower($url), 0, strlen(BASE_URL)) == strtolower(BASE_URL)) {
                $url = substr($url, strlen(BASE_URL));
            }
        }

        if (empty($url)) {
            $url = '/';
        } elseif (substr($url, 0, 1) !== '/') {
            $url = "/$url";
        }

        return $url;
    }

    /**
     * Determine if the cache should be enabled for the current request
     *
     * @param string $url
     * @return boolean
     */
    protected function enabled(HTTPRequest $request)
    {
        $url = self::getUrl($request);

        // Master override
        if (!self::config()->enabled) {
            return false;
        }

        // No GET params other than cache relevant config is passed (e.g. "?stage=Stage"),
        // which would mean that we have to bypass the cache
        if (count(array_diff(array_keys($_GET), ['url']))) {
            return false;
        }

        // Request is not POST (which would have to be handled dynamically)
        if ($_POST) {
            return false;
        }

        // Check url doesn't hit opt out filter
        $optOutURL = self::config()->optOutURL;
        if (!empty($optOutURL) && preg_match($optOutURL, $url)) {
            return false;
        }

        // Check url hits the opt in filter
        $optInURL = self::config()->optInURL;
        if (!empty($optInURL) && !preg_match($optInURL, $url)) {
            return false;
        }

        // Check ajax filter
        if (!self::config()->enableAjax && Director::is_ajax()) {
            return false;
        }

        // Disable caching on staging site
        $isStage = ($stage = Versioned::get_stage()) && ($stage !== 'Live');
        if ($isStage) {
            return false;
        }

        // If user failed BasicAuth, disable cache and fallback to PHP code
        $basicAuthConfig = Config::inst()->get('BasicAuth');
        if (isset($basicAuthConfig->entire_site_protected) && $basicAuthConfig->entire_site_protected) {
            // NOTE(Jake): Required so BasicAuth::requireLogin() doesn't early exit with a 'true' value
            //             This will affect caching performance with BasicAuth turned on.
            if (!DB::is_active()) {
                global $databaseConfig;
                if ($databaseConfig) {
                    DB::connect($databaseConfig);
                }
            }

            // If no DB configured / failed to connect
            if (!DB::is_active()) {
                return false;
            }

            // NOTE(Jake): Required so MemberAuthenticator::record_login_attempt() can call 
            //             Controller::curr()->getRequest()->getIP()
            $stubController = new Controller();
            $stubController->pushCurrent();

            $member = null;
            try {
                $member = BasicAuth::requireLogin($basicAuthConfig->entire_site_protected_message, $basicAuthConfig->entire_site_protected_code, false);
            } catch (HTTPResponse_Exception $e) {
                // This codepath means Member auth failed
            } catch (\Exception $e) {
                // This means an issue occurred elsewhere
                throw $e;
            }
            $stubController->popCurrent();
            // Do not cache because:
            // - $member === true when: "Security::database_is_ready()" is false (No Member tables configured) or unit testing
            // - $member is not a Member object, means the authentication failed.
            if ($member === true || !$member instanceof Member) {
                return false;
            }
        }

        $sessionData = $request->getSession();
        // If displaying form errors then don't display cached result

        if($sessionData) {
            foreach ($sessionData as $field => $data) {
                // Check for session details in the form FormInfo.{$FormName}.errors/FormInfo.{$FormName}.formError
                if ($field === 'FormInfo') {
                    foreach ($data as $formData) {
                        if (isset($formData['errors']) || isset($formData['formError'])) {
                            return false;
                        }
                    }
                }
            }
        }

        // OK!
        return true;
    }

    /**
     * Determine if the specified headers permit this page to be cached
     *
     * @param array $headers
     * @return boolean
     */
    protected function headersAllowCaching(array $headers)
    {

        // Check if any opt out headers are matched
        $optOutHeader = self::config()->optOutHeader;
        if (!empty($optOutHeader)) {
            foreach ($headers as $header) {
                if (preg_match($optOutHeader, $header)) {
                    return false;
                }
            }
        }

        // Check if any opt in headers are matched
        $optInHeaders = self::config()->optInHeader;
        if (!empty($optInHeaders)) {
            foreach ($headers as $header) {
                if (preg_match($optInHeaders, $header)) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }

    /**
     * Returns the caching factory
     *
     * @return Zend_Cache_Core
     */
    protected function getCache()
    {
        // Determine cache parameters
        //$cacheNamespace = self::config()->cacheNamespace;

        return Injector::inst()->get(CacheInterface::class.'.dynamicCache');
    }

    /**
     * Determines identifier by which this page should be identified, given a specific
     * url
     *
     * @param HTTPRequest $request The request
     * @return string The cache key
     */
    protected function getCacheKey(HTTPRequest $request)
    {
        $url = self::getUrl($request);

        $fragments = [];

        // Segment by protocol (always)
        $fragments['protocol'] = Director::protocol();

        // Stage
        $fragments['stage'] = Versioned::get_stage();

        // Segment by hostname if necessary
        if (self::config()->segmentHostname) {
            $fragments['HTTP_HOST'] = $request->getHost();
        }

        // Clean up url to match SS_HTTPRequest::setUrl() interpretation
        $fragments['url'] = preg_replace('|/+|', '/', $url);

        // Extend
        $this->extend('updateCacheKeyFragments', $fragments, $url);

        return "DynamicCache_".md5(implode('|', array_map('md5', $fragments)));
    }

    /**
     * Sends the cached value to the browser, including any necessary headers
     *
     * @param string $cachedValue Serialised cached value
     * @param boolean Flag indicating whether the cache was successful
     */
    protected function createdCachedResponse($cachedValue)
    {
        // Check for empty cache
        if (empty($cachedValue)) {
            return false;
        }
        $deserialisedValue = unserialize($cachedValue);


        // Substitute security id in forms
        $securityID = SecurityToken::getSecurityID();
        $deserialisedValue['content'] = preg_replace(
            '/\<input type="hidden" name="SecurityID" value="\w+"/',
            "<input type=\"hidden\" name=\"SecurityID\" value=\"{$securityID}\"",
            $deserialisedValue['content']
        );
        $response = new HTTPResponse(
            $deserialisedValue['content'],
            $deserialisedValue['response_code']
        );

        // Present cached headers
        foreach ($deserialisedValue['headers'] as $name => $header) {
            $response->addHeader($name, $header);
        }

        // Send success header
        $responseHeader = self::config()->responseHeader;
        if ($responseHeader) {
            $response->addHeader($responseHeader, " hit at ".@date('r'));
            if (self::config()->logHitMiss) {
                Injector::inst()->get(LoggerInterface::class)->info("DynamicCache hit");
            }
        }

        return $response;
    }

    /**
     * Save a page result into the cache
     *
     * @param Zend_Cache_Core $cache
     * @param string $result Page content
     * @param array $headers Headers to cache
     * @param string $cacheKey Key to cache this page under
     */
    protected function cacheResult(HTTPResponse $response, $cacheKey)
    {
        $cacheableHeaders = $this->getCacheableHeaders($response->getHeaders());

        $this->getCache()->set($cacheKey, serialize([
            'headers'       => $cacheableHeaders,
            'response_code' => $response->getStatusCode(),
            'content'       => $response->getBody()
        ]));
    }

    /**
     * Clear the cache
     *
     * @param Zend_Cache_Core $cache
     */
    public function clear()
    {
        $this->getCache()->clear();
    }

    /**
     * Determine which already sent headers should be cached
     *
     * @param array List of sent headers to filter
     * @return array List of cacheable headers
     */
    protected function getCacheableHeaders($headers)
    {
        // Caching options
        $responseHeader = self::config()->responseHeader;
        $cachePattern = self::config()->cacheHeaders;

        $saveHeaders = [];
        foreach ($headers as $header) {

            // Filter out headers starting with $responseHeader
            if ($responseHeader && stripos($header, $responseHeader) === 0) {
                continue;
            }

            // Filter only headers that match the specified pattern
            if ($cachePattern && !preg_match($cachePattern, $header)) {
                continue;
            }

            // Save this header
            $saveHeaders[] = $header;
        }
        return $saveHeaders;
    }

    /**
     * Activate caching on a given url
     *
     * @param string $url
     */
    public function run(HTTPRequest $request, callable $next)
    {
        $enabled = $this->enabled($request);

        if (!$enabled) {
            if (self::config()->logHitMiss) {
                Injector::inst()->get(LoggerInterface::class)->info("DynamicCache skipped");
            }

            /** @var HTTPResponse $response */
            $response = $next($request);

            $responseHeader = self::config()->responseHeader;
            if ($responseHeader) {
                $response->addHeader($responseHeader, 'skipped');
            }

            return $response;
        }

        // First make sure we have session
        if (!isset($_SESSION) && $request->getSession()->requestContainsSessionId($request)) {
            if(!$request->getSession()->isStarted()) {
                $request->getSession()->start($request);
            }
        }

        // Forces the session to be regenerated from $_SESSION
        $session = $request->getSession();
        $session->clearAll();

        // This prevents a new user's security token from being regenerated incorrectly
        //$session->set('SecurityID', SecurityToken::getSecurityID());

        // Set the stage of the website
        // This is normally called in VersionedRequestFilter.
        Versioned::choose_site_stage($request);

        $this->extend('updateEnabled', $enabled);

        // Get cache and cache details
        $cacheKey = $this->getCacheKey($request);

        // Check if cached value can be returned
        $cachedValue = $this->getCache()->get($cacheKey);
        if ($response = $this->createdCachedResponse($cachedValue)) {
            return $response;
        }

        // Check if caching should be short circuted

        /** @var HTTPResponse $response */
        $response = $next($request);

        // Run this page, caching output and capturing data
        if ($responseHeader) {
            if (self::config()->logHitMiss) {
                Injector::inst()->get(LoggerInterface::class)->info("DynamicCache miss");
            }

            $response->addHeader($responseHeader, 'skipped');
        }

        // Skip blank copy unless redirecting
        if($response->isRedirect()) {
            return $response;
        }

        // Skip excluded status codes
        $optInResponseCodes = self::config()->optInResponseCodes;
        $optOutResponseCodes = self::config()->optOutResponseCodes;
        if (is_array($optInResponseCodes) && !in_array($response->getStatusCode(), $optInResponseCodes)) {
            return $response;
        }
        if (is_array($optOutResponseCodes) && in_array($response->getStatusCode(), $optOutResponseCodes)) {
            return $response;
        }

        // Check if any headers match the specified rules forbidding caching
        if (!$this->headersAllowCaching($response->getHeaders())) {
            return $response;
        }

        // Include any "X-Header" sent with this request. This is necessary to
        // ensure that additional CSS, JS, and other files are retained


        // Save data along with sent headers
        $this->cacheResult($response, $cacheKey);

        return $response;
    }
}
