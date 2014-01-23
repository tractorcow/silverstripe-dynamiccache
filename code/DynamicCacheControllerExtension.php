<?php

/**
 * Dynamic caching enhancements for page controller
 *
 * @author Damian Mooyman
 * @package dynamiccache
 */
class DynamicCacheControllerExtension extends Extension {
	public function onBeforeInit() {
		$is_error = is_a($this->owner, 'ErrorPage', true);
		$is_stage = ($stage = Versioned::current_stage()) && ($stage !== 'Live');

		// Set header disabling caching if
		// - current page is error page
		// - current_stage is not live
		if($is_error || $is_stage) {
			$header = DynamicCache::config()->optOutHeaderString;
			header($header);
		}
	}
}
