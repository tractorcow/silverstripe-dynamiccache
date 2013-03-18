# Silverstripe DynamicCache - Simple on the fly caching of dynamic content for Silverstripe

This module seamlessly and robustly caches page content, allowing subsequent requests to bypass
server heavy operations such as database access.

## Credits and Authors

 * Damian Mooyman - <https://github.com/tractorcow/silverstripe-dynamiccache>

## License

 * TODO

## Requirements

 * SilverStripe 3.0
 * PHP 5.3

## Installation Instructions

 * Either extract the module into the dynamiccache folder, or install using composer

```bash
composer require "tractorcow/silverstripe-campaignmonitor": "3.0.*@dev"
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