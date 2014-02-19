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

// If flush, bypass caching completely in order to delegate to Silverstripe's flush protection
if(isset($_GET['flush'])) {
	require('../framework/main.php');
	exit;
}

// Include SilverStripe's core code. This is required to access cache classes
// This is a little less lightweight than the file based cache, but still doesn't
// involve a database hit, but allows for dependency injection
require_once('../framework/core/Core.php');
require_once('code/DynamicCache.php');

// IIS will sometimes generate this.
if(!empty($_SERVER['HTTP_X_ORIGINAL_URL'])) {
	$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
}

/**
 * Figure out the request URL
 */
global $url;

// PHP 5.4's built-in webserver uses this
if (php_sapi_name() == 'cli-server') {
	$url = $_SERVER['REQUEST_URI'];

	// Querystring args need to be explicitly parsed
	if(strpos($url,'?') !== false) {
		list($url, $query) = explode('?',$url,2);
		parse_str($query, $_GET);
		if ($_GET) $_REQUEST = array_merge((array)$_REQUEST, (array)$_GET);
	}

	// Pass back to the webserver for files that exist
	if(file_exists(BASE_PATH . $url) && is_file(BASE_PATH . $url)) return false;

	// Apache rewrite rules use this
} else if (isset($_GET['url'])) {
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
if(strlen($url) && strlen(BASE_URL)) {
	if (substr(strtolower($url), 0, strlen(BASE_URL)) == strtolower(BASE_URL)) {
		$url = substr($url, strlen(BASE_URL));
	}
}

if(empty($url)) {
	$url = '/';
} elseif(substr($url, 0, 1) !== '/') {
	$url = "/$url";
}

// Activate caching here
$instance = DynamicCache::inst();
$instance->run($url);
