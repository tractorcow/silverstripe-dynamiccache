<?php

/**
 * Description of DynamicCache
 *
 * @author Damo
 * 
 * @method static boolean get_enabled()
 * @method static set_enabled(boolean $enabled)
 * @method static string get_optInHeader()
 * @method static set_optInHeader(string $enabled)
 * @method static string get_optOutHeader()
 * @method static set_optOutHeader(string $enabled)
 * @method static string get_optInURL()
 * @method static set_optInURL(string $enabled)
 * @method static string get_optOutURL()
 * @method static set_optOutURL(string $enabled)
 * @method static boolean get_segmentHostname()
 * @method static set_segmentHostname(boolean $enabled)
 * @method static boolean get_enableAjax()
 * @method static set_enableAjax(boolean $enabled)
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

    protected function yield() {
        global $databaseConfig;
        include(dirname(dirname(dirname(__FILE__))) . '/' . FRAMEWORK_DIR . '/main.php');
    }
	
	/**
	 * Activate caching
	 * 
	 * @param string $url
	 */
	public function run($url) {
        if($this->enabled($url)) {
            header('X-DynamicCache-Attempt: true');
        } else {
            header('X-DynamicCache-Attempt: false');
        }
        // @todo - actually cache anything
        $this->yield();
	}
}