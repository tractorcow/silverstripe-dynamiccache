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

        // Activate caching here
        $instance = DynamicCache::inst();
        return $instance->run($request, $next);
    }

}