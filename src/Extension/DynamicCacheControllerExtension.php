<?php

namespace TractorCow\DynamicCache\Extension;

use SilverStripe\Core\Extension;
use SilverStripe\Security\Permission;
use TractorCow\DynamicCache\DynamicCacheMiddleware;


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
        $ignoredClasses = DynamicCacheMiddleware::config()->ignoredPages;
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
            $header = DynamicCacheMiddleware::config()->optOutHeaderString;
            header($header);
        }

        // Flush cache if requested
        if (isset($_GET['cache']) && ($_GET['cache'] === 'flush') && Permission::check('ADMIN')) {
            DynamicCacheMiddleware::inst()->clear();
        }
    }
}
