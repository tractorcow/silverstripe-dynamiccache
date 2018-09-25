<?php

namespace TractorCow\DynamicCache;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Permission;

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
        /** @var HTTPRequest $request */
        $request = Controller::curr()->getRequest();

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

            /** @var HTTPResponse $response */
            $response = Controller::curr()->getResponse();
            $response->addHeader($header, 'true');
        }

        // Flush cache if requested
        if (
            $request->getVar('cache') &&
            $request->getVar('cache') === 'flush' &&
            Permission::check('ADMIN')
        ) {
            DynamicCache::inst()->clear();
        }

    }
}
