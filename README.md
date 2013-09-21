# Silverstripe DynamicCache - Simple on the fly caching of dynamic content for Silverstripe

This module seamlessly and robustly caches page content, allowing subsequent requests to bypass
server heavy operations such as database access.

## Credits and Authors

 * Damian Mooyman - <https://github.com/tractorcow/silverstripe-dynamiccache>

## How it works

When a page is requested by a visitor the module will attempt to return any cached
content / custom headers for that request before the database connection is initiated.
If a cached copy exists and can be returned, this can save a huge amount of processing
overhead.

If a cached copy does not exist, then the page will process normally, but the result
will then be saved for later page requests.

This differs from StaticPublisher or StaticExporter in that there is no administration
caching. The caching is done incrementally on a per-page request basis rather than
up front. This distributes the caching task across several requests, adding only a
trivial overhead to non-cached requests, but saving a huge amount of time at the
administration level.

Whenever a page is published the entire cache is cleared for the sake of robustness.

This module will allow individual pages to opt-out of caching by specifying certain headers,
and will ignore caching on ajax pages or direct requests to controllers (including 
form submissions) by checking for any url-segments that start with an uppercase letter.

## Customising Cache Behaviour

If you extend DynamicCache you can hook into two additional methods. A helper extension class `DynamicCacheExtension`
can be used here to get started.

The below example will allow the cache to be bypassed if a certain session value is set, and segments the cache between
mobile / non-mobile users (assuming silverstripe/mobile module is installed).

```php

	CacheCustomisation extends DynamicCacheExtension {
		public function updateEnabled(&$enabled) {
			if(Session::get('Uncachable') {
				$enabled = false; // Disable caching for this request
			}
		}

		public function updateCacheKeyFragments(array &$fragments) {
			// For any url segment cache between mobile and desktop devices.
			$fragments[] = MobileBrowserDetector::is_mobile() ? 'mobile' : 'desktop';
		}
	}

```

## Important stuff!

Please note that this module DISABLES CSRF in order to allow cached forms to function
between user sessions. This may be fixed in a future release (perhaps by substituting
CSRF values during retrieval of cached pages).

## Requirements

 * SilverStripe 3.1
 * PHP 5.3

## Installation Instructions

 * Either extract the module into the dynamiccache folder, or install using composer

```bash
composer require "tractorcow/silverstripe-dynamiccache": "3.1.*@dev"
```

 * Edit your .htaccess (or web.config, etc) to redirect requests to the dynamiccache/cache-main.php
   file instead of the framework/main.php file

```apache
RewriteRule .* dynamiccache/cache-main.php?url=%1&%{QUERY_STRING} [L]
```

Configuration can be done by the normal Silverstripe built in configuration system.
See [dynamiccache.yml](_config/dynamiccache.yml) for the list of configurable options.

## Configuration options

 * enabled - (boolean) Global override. Turn to false to turn caching off.
 * optInHeader - (null|string) If a header should be used to opt in to caching,
   set the regular expression here which will match the specified header.
 * optOutHeader - (null|string) If a header should be used to explicitly disable
   caching for a cache, set the regular expression here which will be used to
   match the specified header. E.g. '/^X\-DynamicCache\-OptOut/'
 * responseHeader - (null|string) Header prefix to use for reporting cache results
 * optInURL - (null|string) If caching should be limited only to specified urls
   set the regular expression here which will be used to match those urls
 * optOutURL - (null|string) If caching should be disabled for specified urls 
   set the regular expression here which will be used to match those urls
   E.g. '/(^\/admin)|(\/[A-Z])/'
 * segmentHostname - (boolean) Determine if caching should be separated for
   different hostnames. Important if running off a system that serves different
   content for different hostname, but still uses the same backend, such as the
   subsites module
 * enableAjax - (boolean) Determine if caching should be enabled during ajax
 * cacheDuration - (null|integer) Duration of the page cache, in seconds (default is 1 hour).
 * cacheHeaders - (null|string) Determines which headers should also be cached.
   X-Include-CSS and other relevant headers can be essential in instructing the
   front end to include specific resource files. E.g. '/^X\-/i'
 * cacheBackend - (null|string) If you wish to override the cache configuration,
   then change this to another backend, and initialise a new SS_Cache backend
   in your _config file

## License

Copyright (c) 2013, Damian Mooyman
All rights reserved.

All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
 * The name of Damian Mooyman may not be used to endorse or promote products
   derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
