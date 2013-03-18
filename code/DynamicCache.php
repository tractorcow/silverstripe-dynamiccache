<?php

/**
 * Description of DynamicCache
 *
 * @author Damo
 * 
 * @method static boolean get_enabled()
 * @method static set_enabled(boolean $enabled)
 * @method static string get_optInHeader()
 * @method static set_optInHeader(string $header)
 * @method static string get_optOutHeader()
 * @method static set_optOutHeader(string $header)
 * @method static string get_optInURL()
 * @method static set_optInURL(string $url)
 * @method static string get_optOutURL()
 * @method static set_optOutURL(string $url)
 * @method static boolean get_segmentHostname()
 * @method static set_segmentHostname(boolean $segmentHostname)
 * @method static boolean get_enableAjax()
 * @method static set_enableAjax(boolean $enabled)
 * @method static integer get_cacheDuration()
 * @method static set_cacheDuration(integer $duration)
 * @method static string get_responseHeader();
 * @method static set_responseHeader(string $header)
 * @method static string get_cacheHeaders()
 * @method static set_cacheHeaders(string $header)
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

    protected function yield() {
        global $databaseConfig;
        include(dirname(dirname(dirname(__FILE__))) . '/' . FRAMEWORK_DIR . '/main.php');
    }
	
	/**
	 * Returns the  caching factory
	 * 
	 * @return Zend_Cache_Core|Zend_Cache_Frontend
	 */
	protected function getCache() {
		$factory = SS_Cache::factory('DynamicCache');
		$factory->setLifetime(self::get_cacheDuration());
		return $factory;
	}
	
	/**
	 * Determines the url to cache against the current url
	 * 
	 * @param string $url
	 */
	protected function getCacheKey($url) {
		$cacheKey = trim($url, '/');
		return 'DynamicCache_' . md5($cacheKey ? $cacheKey : 'home');
	}
	
	protected function getCachedValue($cacheKey, $cache) {

		// Check if cached value exists, and if so return it
		return $cache->load($cacheKey);
	}
	
	/**
	 * Sends the cached value to the browser, including any necessary headers
	 * 
	 * @param array $cachedValue
	 */
	protected function presentCachedvalue($cachedValue) {
		$deserialisedValue = unserialize($cachedValue);
		header(self::get_responseHeader() . ': hit at ' . @date('r'));
		foreach($deserialisedValue['headers'] as $header) {
			header($header);
		}
		echo $deserialisedValue['content'];
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
		if( ($_REQUEST['flush'] === 'all' || $_REQUEST['flush'] === 'cache')
			&& (Director::isDev() || Permission::check('ADMIN'))
		) {
			$cache->clean();
		}
		
		// Disable CSRF - It doesn't work with cached security tokens shared across sessions
		SecurityToken::disable();
		
		// Check if caching should be short circuted
        if(!$this->enabled($url)) {
            header("$responseHeader: skipped");
			$this->yield();
			return;
        }
		
		// Check if cached value can be returned
		$cachedValue = $cache->load($cacheKey);
		
		// Present cached data
		if($cachedValue) {
			$this->presentCachedValue($cachedValue);
			return;
		}
		
		// Run this page, caching output and capturing data
		header("$responseHeader: miss at " . @date('r'));
		
		ob_start();
        $this->yield();
		$headers = headers_list();
		$result = ob_get_flush();
		
		// Check if any headers match the specified rules forbidding caching
		if(!$this->headersAllowCaching($headers)) return;
		
		// Include any "X-Header" sent with this request. This is necessary to
		// ensure that additional CSS, JS, and other files are retained
		$saveHeaders = array();
		$cachePattern = self::get_cacheHeaders();
		if($cachePattern) foreach($headers as $header) {
			// Only cache headers that match the requested pattern, excluding those
			// used by DynamicCache itself
			if(stripos($header, $responseHeader) !== 0 && preg_match($cachePattern, $header)) {
				$saveHeaders[] = $header;
			}
		}
		
		// Save data along with sent headers
		$cache->save(serialize(array(
			'headers' => $saveHeaders,
			'content' => $result
		)), $cacheKey);
	}
}