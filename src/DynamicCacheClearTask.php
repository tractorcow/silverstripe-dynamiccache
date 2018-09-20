<?php

/**
 * Clears the cache
 *
 * @author Jake Bentvelzen
 * @package dynamiccache
 */
class DynamicCacheClearTask extends BuildTask
{
    protected $title = "DynamicCache Clear Task";
    
    protected $description = "This task clears the entire DynamicCache";
    
    public function run($request) {
        DynamicCache::inst()->clear();
        echo 'DynamicCache has been cleared.';
    }
}
