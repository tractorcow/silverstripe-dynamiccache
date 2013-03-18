<?php

/**
 * Handles on the fly caching of pages
 *
 * @author Damian Mooyman
 * 
 * @method static boolean get_enabled() Determine if the cache is enabled
 * @method static set_enabled(boolean $enabled) Set the enabled state of the cache
 * @method static string get_optInHeader() Get the regular expression to use if a header must be used to opt into caching
 * @method static set_optInHeader(string $header) Set the regular expression to use if a header must be used to opt into caching
 * @method static string get_optOutHeader() Get the regular expression to use if a header must be used to opt out of caching
 * @method static set_optOutHeader(string $header) Set the regular expression to use if a header must be used to opt out of caching
 * @method static string get_optInURL() Get the regular expression for urls that may be cached
 * @method static set_optInURL(string $url) Set the regular expression for urls that may be cached
 * @method static string get_optOutURL() Get the regular expression for urls that may not be cached
 * @method static set_optOutURL(string $url) Set the regular expression for urls that may not be cached
 * @method static boolean get_segmentHostname() Determine if the page cache should be segmented by hostname
 * @method static set_segmentHostname(boolean $segmentHostname) Set if the page cache should be segmented by hostname
 * @method static boolean get_enableAjax() Determine if caching should be enabled during ajax
 * @method static set_enableAjax(boolean $enabled) Set if caching should be enabled during ajax
 * @method static integer get_cacheDuration() Get duration of cache in seconds
 * @method static set_cacheDuration(integer $duration) Set duration of cache in seconds
 * @method static string get_responseHeader() Get header name to use when reporting caching success
 * @method static set_responseHeader(string $header) Set header name to use when reporting caching success
 * @method static string get_cacheHeaders() Get regular expression to use when determining which headers to cache
 * @method static set_cacheHeaders(string $header) Set regular expression to use when determining which headers to cache
 */
class DynamicCache extends Object {

	/**
	 * Shortcut for handling configuration parameters
	 */
	public function __call($name, $arguments) {
		if(preg_match('/^(?<op>(get)|(set))_(?<arg>.+)$/', $name, $matches)) {
			if($matches['op'] === 'set') {
				Config::inst()->update('DynamicCache', $matches['arg'], $arguments[0]);
			} else {
				return Config::inst()->get('DynamicCache', $matches['arg']);
			}
		}
		return parent::__call($method, $arguments);
	}

	/**
	 * Instance of DynamicCache
	 *
	 * @var DynamicCache
	 */
	static protected $instance;
	
	/**
	 * Return the current cache instance
	 * 
	 * @return DynamicCache
	 */
	public static function inst() {
		if (!self::$instance) self::$instance = new DynamicCache();
		return self::$instance;
	}
	
	/**
	 * Determine if the cache should be enabled for the current request
	 * 
	 * @param string $url
	 * @return boolean
	 */
	protected function enabled($url) {
		
		// Master override
		if(!self::get_enabled()) return false;
		
		// No GET params other than cache relevant config is passed (e.g. "?stage=Stage"),
		// which would mean that we have to bypass the cache
		if(count(array_diff(array_keys($_GET), array('url')))) return false;
		
		// Request is not POST (which would have to be handled dynamically)
		if($_POST) return false;
		
		// Check url doesn't hit opt out filter
		$optOutURL = self::get_optOutURL();
		if(!empty($optOutURL) && preg_match($optOutURL, $url)) return false;

		// Check url hits the opt in filter
		$optInURL = self::get_optInURL();
		if(!empty($optInURL) && !preg_match($optInURL, $url)) return false;
		
        // Check ajax filter
        if(!self::get_enableAjax() && Director::is_ajax()) return false;

        // OK!
        return true;
	}
	
	/**
	 * Determine if the specified headers permit this page to be cached
	 * 
	 * @param array $headers
	 * @return boolean
	 */
	protected function headersAllowCaching(array $headers) {
		
		// Check if any opt out headers are matched
		$optOutHeader = self::get_optOutHeader();
		if(!empty($optOutHeader)) {
			foreach($headers as $header) {
				if(preg_match($optOutHeader, $header)) return false;
			}
		}

		// Check if any opt in headers are matched
		$optInHeaders = self::get_optInHeader();
		if(!empty($optInHeaders)) {
			foreach($headers as $header) {
				if(preg_match($optInHeaders, $header)) return true;
			}
			return false;
		}
		
		return true;
	}

	/**
	 * Returns control of page rendering to SilverStripe
	 */
    protected function yield() {
        global $databaseConfig;
        include(dirname(dirname(dirname(__FILE__))) . '/' . FRAMEWORK_DIR . '/main.php');
    }
	
	/**
	 * Returns the caching factory
	 * 
	 * @return Zend_Cache_Core|Zend_Cache_Frontend
	 */
	protected function getCache() {
		$factory = SS_Cache::factory('DynamicCache');
		$factory->setLifetime(self::get_cacheDuration());
		return $factory;
	}
	
	/**
	 * Determines identifier by which this page should be identified, given a specific
	 * url
	 * 
	 * @param string $url The request URL
	 * @return string The cache key
	 */
	protected function getCacheKey($url) {
		$hostKey = self::get_segmentHostname() ? $_SERVER['HTTP_HOST'] : '';
		$url = trim($url, '/');
		$urlKey = $url ? $url : 'home';
		return "DynamicCache_" . md5("{$hostKey}/{$urlKey}");
	}
	
	/**
	 * Sends the cached value to the browser, including any necessary headers
	 * 
	 * @param string $cachedValue Serialised cached value
	 * @param boolean Flag indicating whether the cache was successful
	 */
	protected function presentCachedResult($cachedValue) {
		
		// Check for empty cache
		if(empty($cachedValue)) return false;
		$deserialisedValue = unserialize($cachedValue);
		if(empty($deserialisedValue['content'])) return false;
		
		// Present cached headers
		foreach($deserialisedValue['headers'] as $header) {
			header($header);
		}
		
		// Send success header
		$responseHeader = self::get_responseHeader();
		if($responseHeader) header("$responseHeader: hit at " . @date('r'));
		
		// Present content
		echo $deserialisedValue['content'];
		return true;
	}
	
	/**
	 * Save a page result into the cache
	 * 
	 * @param Zend_Cache_Core|Zend_Cache_Frontend $cache
	 * @param string $result Page content
	 * @param array $headers Headers to cache
	 * @param string $cacheKey Key to cache this page under
	 */
	protected function cacheResult($cache, $result, $headers, $cacheKey) {
		$cache->save(serialize(array(
			'headers' => $headers,
			'content' => $result
		)), $cacheKey);
	}
	
	/**
	 * Clear the cache
	 * 
	 * @param Zend_Cache_Core|Zend_Cache_Frontend $cache
	 */
	public function clear($cache = null) {
		if(empty($cache)) $cache = $this->getCache();
		$cache->clean();
	}
	
	/**
	 * Checks instruction from the site admin to the content cache
	 * When logged in use flush=all or flush=cache to clear the cache
	 * 
	 * @param Zend_Cache_Core|Zend_Cache_Frontend $cache
	 */
	protected function checkCacheCommands($cache) {
		if( isset($_REQUEST['flush'])
			&& ($_REQUEST['flush'] === 'all' || $_REQUEST['flush'] === 'cache')
			&& (Director::isDev() || Permission::check('ADMIN'))
		) {
			$this->clear($cache);
		}
	}
	
	/**
	 * Determine which already sent headers should be cached
	 * 
	 * @param array List of sent headers to filter
	 * @return array List of cacheable headers
	 */
	protected function getCacheableHeaders($headers) {
		// Caching options
		$responseHeader = self::get_responseHeader();
		$cachePattern = self::get_cacheHeaders();
		
		$saveHeaders = array();
		foreach($headers as $header) {
			
			// Filter out headers starting with $responseHeader
			if($responseHeader && stripos($header, $responseHeader) === 0) {
				continue;
			}
			
			// Filter only headers that match the specified pattern
			if($cachePattern && !preg_match($cachePattern, $header)) {
				continue;
			}
			
			// Save this header
			$saveHeaders[] = $header;
		}
		return $saveHeaders;
	}

	/**
	 * Activate caching
	 * 
	 * @param string $url
	 */
	public function run($url) {
		// Get cache and cache details
		$responseHeader = self::get_responseHeader();
		$cache = $this->getCache();
		$cacheKey = $this->getCacheKey($url);
		
		// Clear cache if flush = cache or all
		$this->checkCacheCommands($cache);
		
		// Disable CSRF - It doesn't work with cached security tokens shared across sessions
		SecurityToken::disable();
		
		// Check if caching should be short circuted
        if(!$this->enabled($url)) {
            if($responseHeader) header("$responseHeader: skipped");
			$this->yield();
			return;
        }
		
		// Check if cached value can be returned
		$cachedValue = $cache->load($cacheKey);
		if($this->presentCachedResult($cachedValue)) return;
		
		// Run this page, caching output and capturing data
		if($responseHeader) header("$responseHeader: miss at " . @date('r'));
		
		ob_start();
        $this->yield();
		$headers = headers_list();
		$result = ob_get_flush();
		
		// Skip blank copy
		if(empty($result)) return;
		
		// Check if any headers match the specified rules forbidding caching
		if(!$this->headersAllowCaching($headers)) return;
		
		// Include any "X-Header" sent with this request. This is necessary to
		// ensure that additional CSS, JS, and other files are retained
		$saveHeaders = $this->getCacheableHeaders($headers);
		
		// Save data along with sent headers
		$this->cacheResult($cache, $result, $saveHeaders, $cacheKey);
	}
}