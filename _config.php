<?php
/**
 * Created by priyashantha@silverstripers.com
 * Date: 9/20/18
 * Time: 4:10 PM
 */

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use TractorCow\DynamicCache\DynamicCacheMiddleware;

if (DynamicCacheMiddleware::config()->logHitMiss) {
    $logger = Injector::inst()->get(LoggerInterface::class);
    if ($logger instanceof Logger) {
        $logger->pushHandler(new StreamHandler(BASE_PATH.'/dynamic_cache.log', Logger::INFO));
    }
}