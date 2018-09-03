<?php

namespace TractorCow\DynamicCache;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;

/**
 * Abstract extension class to customise the behaviour of DynamicCache.
 *
 * @author Matthias Krauser
 * @package dynamiccache
 */
class DynamicCacheMiddleware implements HTTPMiddleware
{

    /**
     * Generate response for the given request
     *
     * @param HTTPRequest $request
     * @param callable $delegate
     * @return HTTPResponse
     */
    public function process(HTTPRequest $request, callable $next)
    {
        // If flush, bypass caching completely in order to delegate to Silverstripe's flush protection
        if ($request->offsetExists('flush')) {
            return $next($request);
        }

        $url = $request->getURL(true);

        // Remove base folders from the URL if webroot is hosted in a subfolder
        if (strlen($url) && strlen(BASE_URL)) {
            if (substr(strtolower($url), 0, strlen(BASE_URL)) == strtolower(BASE_URL)) {
                $url = substr($url, strlen(BASE_URL));
            }
        }

        if (empty($url)) {
            $url = '/';
        } elseif (substr($url, 0, 1) !== '/') {
            $url = "/$url";
        }

        // Activate caching here
        $instance = DynamicCache::inst();
        return $instance->run($request, $next);
    }

}