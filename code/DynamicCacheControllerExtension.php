<?php

/**
 * Dynamic caching enhancements for page controller
 *
 * @author Damian Mooyman
 * @package dynamiccache
 */
class DynamicCacheControllerExtension extends Extension {
	public function onBeforeInit() {

		// Detect cache avoidance conditions
		$ignored_by_class = is_a($this->owner->data(), 'ErrorPage', true) ||
		                    is_a($this->owner, 'UserDefinedForm', true);
		$is_stage = ($stage = Versioned::current_stage()) && ($stage !== 'Live');

		// Set header disabling caching if
		// - current page is error page
		// - current_stage is not live
		if($ignored_by_class || $is_stage) {
			$header = DynamicCache::config()->optOutHeaderString;
			header($header);
		}

		// Flush cache if requested
		if( isset($_GET['flush'])
			|| (isset($_GET['cache']) && ($_GET['cache'] === 'flush') && Permission::check('ADMIN'))
		) {
			DynamicCache::inst()->clear();
		}
	}
}
