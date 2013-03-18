<?php

/**
 * Ensures that pages are correctly flushed from the cache on publish
 *
 * @author Damo
 */
class DynamicCachePageExtension extends SiteTreeExtension {
	
	/**
	 * Clear the entire dynamic cache once a page has been published.
	 * Safe and dirty.
	 * 
	 * @param SiteTree $original
	 */
	public function onAfterPublish(&$original) {
		DynamicCache::inst()->clear();
	}
}