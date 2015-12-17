<?php

/**
 * Dynamic caching enhancements for page controller
 *
 * @author Damian Mooyman
 * @package dynamiccache
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
        $isStage = ($stage = Versioned::current_stage()) && ($stage !== 'Live');

        // Set header disabling caching if
        // - current page is an ignored page type
        // - current_stage is not live
        if ($ignoredByClass || $isStage) {
            $header = DynamicCache::config()->optOutHeaderString;
            header($header);
        }

        // Flush cache if requested
        if (isset($_GET['flush'])
            || (isset($_GET['cache']) && ($_GET['cache'] === 'flush') && Permission::check('ADMIN'))
        ) {
            DynamicCache::inst()->clear();
        }
    }
}
