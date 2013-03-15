<?php

/**
 * This file acts as the front end buffer between Silverstripe and the dynamic caching
 * mechanism.
 * 
 * Your .htaccess will need to be modified to change your framework/main.php reference
 * to this (dynamiccache/cache-main.php). Alternatively you may set this to a custom file,
 * set the various optional filters ($optInXXX etc) and include this file directly after,
 * making sure to set $overrideCacheOptions directly before
 * 
 * Caching will automatically filter via top level domain so that modules that serve
 * domain specific content (such as subsites) will silently work.
 * 
 * If a page should not be cached, then 
 */

// Include SilverStripe's core code. This is required to access cache classes
// This is a little less lightweight than the file based cache, but still doesn't
// involve a database hit, but allows for dependency injection
require_once('../framework/core/Core.php');
require_once('code/DynamicCache.php');

// IIS will sometimes generate this.
if(!empty($_SERVER['HTTP_X_ORIGINAL_URL'])) {
	$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
}

// Apache rewrite rules use this
if (isset($_GET['url'])) {
	$url = $_GET['url'];
	// IIS includes get variables in url
	$i = strpos($url, '?');
	if($i !== false) {
		$url = substr($url, 0, $i);
	}
	
// Lighttpd uses this
} else {
	if(strpos($_SERVER['REQUEST_URI'],'?') !== false) {
		list($url, $query) = explode('?', $_SERVER['REQUEST_URI'], 2);
		parse_str($query, $_GET);
		if ($_GET) $_REQUEST = array_merge((array)$_REQUEST, (array)$_GET);
	} else {
		$url = $_SERVER["REQUEST_URI"];
	}
}

// Remove base folders from the URL if webroot is hosted in a subfolder
if (substr(strtolower($url), 0, strlen(BASE_URL)) == strtolower(BASE_URL)) $url = substr($url, strlen(BASE_URL));

// Activate caching here
$instance = DynamicCache::inst();
$instance->run($url);

/*

if (
	$dynamicCacheOptions['enabled']
	// No GET params other than cache relevant config is passed (e.g. "?stage=Stage"),
	// which would mean that we have to bypass the cache
	&& count(array_diff(array_keys($_GET), array('url'))) == 0
	// Request is not POST (which would have to be handled dynamically)
	&& count($_POST) == 0
	// Check url doesn't hit opt out filter
	&& (empty($dynamicCacheOptions['optOutURL']) || !preg_match($dynamicCacheOptions['optOutURL'], $_GET['url']))
	// Check url hits the opt in filter
	&& (empty($dynamicCacheOptions['optInURL']) || preg_match($dynamicCacheOptions['optInURL'], $_GET['url']))
	// Check ajax filter
	&& ($dynamicCacheOptions['enableAjax'] || !$isAjax)
	// Header based filters will be calculated later
) {
	// Define system paths (copied from Core.php)
	if(!defined('BASE_PATH')) {
		// Assuming that this file is framework/static-main.php we can then determine the base path
		define('BASE_PATH', rtrim(dirname(dirname(__FILE__))), DIRECTORY_SEPARATOR);
	}
	if(!defined('BASE_URL')) {
		// Determine the base URL by comparing SCRIPT_NAME to SCRIPT_FILENAME and getting common elements
		$path = realpath($_SERVER['SCRIPT_FILENAME']);
		if(substr($path, 0, strlen(BASE_PATH)) == BASE_PATH) {
			$urlSegmentToRemove = preg_replace('/\\\\/', '/', substr($path, strlen(BASE_PATH)));
			if(substr($_SERVER['SCRIPT_NAME'], -strlen($urlSegmentToRemove)) == $urlSegmentToRemove) {
				$baseURL = substr($_SERVER['SCRIPT_NAME'], 0, -strlen($urlSegmentToRemove));
				define('BASE_URL', rtrim($baseURL, DIRECTORY_SEPARATOR));
			}
		}
	}
	
	$url = $_GET['url'];
	$hostname = $_SERVER['HTTP_HOST'];
	
	// Remove base folders from the URL if webroot is hosted in a subfolder
	if (substr(strtolower($url), 0, strlen(BASE_URL)) == strtolower(BASE_URL)) {
		$url = substr($url, strlen(BASE_URL));
	}
	
	// Determine cache key and initialise cache
	$cacheKey = trim($url, '/');
	$cacheKey = 'DynamicCache_' . md5($cacheKey ? $cacheKey : 'home');
	$cache = SS_Cache::factory('DynamicCache');
	
	// Check if cached value exists, and if so return it
	
	// Encode each part of the path individually, in order to support multibyte paths.
	// SiteTree.URLSegment and hence the static folder and filenames are stored in encoded form,
	// to avoid filesystem incompatibilities.
	$file = implode('/', array_map('rawurlencode', explode('/', $file)));
	// Find file by extension (either *.html or *.php)
	if (file_exists($cacheBaseDir . $cacheDir . $file . '.html')) {
		header('X-SilverStripe-Cache: hit at '.@date('r'));
		echo file_get_contents($cacheBaseDir . $cacheDir . $file . '.html');
		if ($cacheDebug) echo "<h1>File was cached</h1>";
	} elseif (file_exists($cacheBaseDir . $cacheDir . $file . '.php')) {
		header('X-SilverStripe-Cache: hit at '.@date('r'));
		include_once $cacheBaseDir . $cacheDir . $file . '.php';
		if ($cacheDebug) echo "<h1>File was cached</h1>";
	} else {
		header('X-SilverStripe-Cache: miss at '.@date('r') . ' on ' . $cacheDir . $file);
		// No cache hit... fallback to dynamic routing
		include '../framework/main.php';
		if ($cacheDebug) echo "<h1>File was NOT cached</h1>";
	}
} else {
	// Fall back to dynamic generation via normal routing if caching has been explicitly disabled
	include '../framework/main.php';
}

*/