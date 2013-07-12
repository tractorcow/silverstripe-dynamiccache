<?php

/**
 * Dynamic caching enhancements for page controller
 *
 * @author Damian Mooyman
 * @package dynamiccache
 */
class DynamicCacheControllerExtension extends Extension {
	
	public function onBeforeInit() {
		
		// If not on live site, set header disabling caching to prevent caching of draft content
		if(($stage = Versioned::current_stage()) && ($stage !== 'Live')) {
			$header = DynamicCache::config()->optOutHeaderString;
			header($header);
		}
	}
}
