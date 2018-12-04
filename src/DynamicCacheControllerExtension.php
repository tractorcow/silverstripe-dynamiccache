<?php

namespace TractorCow\DynamicCache;

use Extension;
use Permission;


/**
 * Dynamic caching enhancements for page controller
 *
 * @author Damian Mooyman
 * @package dynamiccache
 */

/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: upgrade to SS4
  * OLD:  extends Extension (ignore case)
  * NEW:  extends Extension (COMPLEX)
  * EXP: Check for use of $this->anyVar and replace with $this->anyVar[$this->owner->ID] or consider turning the class into a trait
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
class DynamicCacheControllerExtension extends Extension
{
    public function onBeforeInit()
    {

        // Determine if this page is of a non-cacheable type
        $ignoredClasses = DynamicCache::config()->ignoredPages;
        $ignoredByClass = false;
        if ($ignoredClasses) {
            foreach ($ignoredClasses as $ignoredClass) {
                if (is_a($this->owner->data(), $ignoredClass, true)) {
                    $ignoredByClass = true;
                    break;
                }
            }
        }

        // Set header disabling caching if
        // - current page is an ignored page type
        // - current_stage is not live
        if ($ignoredByClass) {
            $header = DynamicCache::config()->optOutHeaderString;
            header($header);
        }

        // Flush cache if requested
        if (isset($_GET['cache']) && ($_GET['cache'] === 'flush') && Permission::check('ADMIN')) {
            DynamicCache::inst()->clear();
        }
    }
}
