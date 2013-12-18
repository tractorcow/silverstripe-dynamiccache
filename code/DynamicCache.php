<?php

/**
 * Handles on the fly caching of pages
 *
 * @author Damian Mooyman
 * @package dynamiccache
 */
class DynamicCache extends Object {

	/**
	 * Shortcut for handling configuration parameters
	 */
	public function __call($name, $arguments) {
		if(preg_match('/^(?<op>(get)|(set))_(?<arg>.+)$/', $name, $matches)) {
			$field = $matches['arg'];
			Deprecation::notice('3.1', "Call DynamicCache::config()->$field directly");
			if($matches['op'] === 'set') {
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
	static protected $instance;

	/**
	 * Return the current cache instance
	 *
	 * @return DynamicCache
	 */
	public static function inst() {
		if (!self::$instance) self::$instance = DynamicCache::create();
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
		if(!self::config()->enabled) return false;

		// No GET params other than cache relevant config is passed (e.g. "?stage=Stage"),
		// which would mean that we have to bypass the cache
		if(count(array_diff(array_keys($_GET), array('url')))) return false;

		// Request is not POST (which would have to be handled dynamically)
		if($_POST) return false;

		// Check url doesn't hit opt out filter
		$optOutURL = self::config()->optOutURL;
		if(!empty($optOutURL) && preg_match($optOutURL, $url)) return false;

		// Check url hits the opt in filter
		$optInURL = self::config()->optInURL;
		if(!empty($optInURL) && !preg_match($optInURL, $url)) return false;

		// Check ajax filter
		if(!self::config()->enableAjax && Director::is_ajax()) return false;

		// If displaying form errors then don't display cached result
		foreach(Session::get_all() as $field => $data) {
			// Check for session details in the form FormInfo.{$FormName}.errors
			if($field === 'FormInfo') {
				foreach($data as $formData) {
					if(isset($formData['errors'])) return false;
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
	protected function headersAllowCaching(array $headers) {

		// Check if any opt out headers are matched
		$optOutHeader = self::config()->optOutHeader;
		if(!empty($optOutHeader)) {
			foreach($headers as $header) {
				if(preg_match($optOutHeader, $header)) return false;
			}
		}

		// Check if any opt in headers are matched
		$optInHeaders = self::config()->optInHeader;
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
	 * @return Zend_Cache_Core
	 */
	protected function getCache() {

		// Determine cache parameters
		$backend = self::config()->cacheBackend;

		// Create default backend if not overridden
		if($backend === 'DynamicCache') {

			// Using own folder helps with separating page cache from other SS cached elements
			$cacheDir = TEMP_FOLDER . DIRECTORY_SEPARATOR . 'dynamic_cache';
			if (!is_dir($cacheDir)) mkdir($cacheDir);
			SS_Cache::add_backend('DynamicCacheStore', 'File', array('cache_dir' => $cacheDir));
			SS_Cache::pick_backend('DynamicCacheStore', $backend, 1000);
		}

		// Set lifetime, allowing for 0 (infinite) lifetime
		if(($lifetime = self::config()->cacheDuration) !== null) {
			SS_Cache::set_cache_lifetime($backend, $lifetime);
		}

		// Get factory from this cache
		return SS_Cache::factory($backend);
	}

	/**
	 * Determines identifier by which this page should be identified, given a specific
	 * url
	 *
	 * @param string $url The request URL
	 * @return string The cache key
	 */
	protected function getCacheKey($url) {
		$fragments = array();

		// Segment by protocol (always)
		$fragments[] = Director::protocol();

		// Segment by hostname if necessary
		if(self::config()->segmentHostname) {
			$fragments[] = $_SERVER['HTTP_HOST'];
		}

		// Segment by url
		$url = trim($url, '/');
		$fragments[] = $url ? $url : 'home';

		// Extend
		$this->extend('updateCacheKeyFragments', $fragments);

		return "DynamicCache_" . md5(implode('|', array_map('md5', $fragments)));
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
		$responseHeader = self::config()->responseHeader;
		if($responseHeader) header("$responseHeader: hit at " . @date('r'));

		// Present content
		echo $deserialisedValue['content'];
		return true;
	}

	/**
	 * Save a page result into the cache
	 *
	 * @param Zend_Cache_Core $cache
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
	 * @param Zend_Cache_Core $cache
	 */
	public function clear($cache = null) {
		if(empty($cache)) $cache = $this->getCache();
		$cache->clean();
	}

	/**
	 * Checks instruction from the site admin to the content cache
	 * When logged in use flush=all or flush=cache to clear the cache
	 *
	 * @param Zend_Cache_Core $cache
	 */
	protected function checkCacheCommands($cache) {
		$flushCommand = isset($_REQUEST['flush']) && ($_REQUEST['flush'] === 'all' || $_REQUEST['flush'] === 'cache');
		$cacheCommand = isset($_REQUEST['cache']) && $_REQUEST['cache'] === 'flush';
		$hasPermission = Director::isDev() || Session::get("loggedInAs");
		if(($flushCommand || $cacheCommand) && $hasPermission) {
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
		$responseHeader = self::config()->responseHeader;
		$cachePattern = self::config()->cacheHeaders;

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
	 * Activate caching on a given url
	 *
	 * @param string $url
	 */
	public function run($url) {
		// First make sure we have session
		if(!isset($_SESSION)) Session::start();
		Session::clear_all(); // Forces the session to be regenerated from $_SESSION

		// Get cache and cache details
		$responseHeader = self::config()->responseHeader;
		$cache = $this->getCache();
		$cacheKey = $this->getCacheKey($url);

		// Clear cache if flush = cache or all
		$this->checkCacheCommands($cache);

		// Disable CSRF - It doesn't work with cached security tokens shared across sessions
		SecurityToken::disable();

		// Check if caching should be short circuted
		$enabled = $this->enabled($url);
		$this->extend('updateEnabled', $enabled);
		if(!$enabled) {
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
