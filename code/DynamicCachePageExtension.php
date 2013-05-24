<?php

/**
 * Ensures that pages are correctly flushed from the cache on publish
 *
 * @author Damian Mooyman
 * @package dynamiccache
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
	
	/**
	 * Clear the entire dynamic cache once a page has been unpublished.
	 * Safe and dirty.
	 * 
	 * @param SiteTree $original
	 */
	public function onAfterUnpublish() {
		DynamicCache::inst()->clear();
	}
	
	/**
	 * Clear the entire dynamic cache once a page has been deleted.
	 * Safe and dirty.
	 * 
	 * @param SiteTree $original
	 */
	public function onBeforeDelete() {
		DynamicCache::inst()->clear();
	}
}
