<?php

/**
 * Abstract extension class to customise the behaviour of DynamicCache.
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
abstract class DynamicCacheExtension extends Extension
{
    
    /**
     * Alters whether or not caching will be enabled for a particular request. If not enabled, no cache value will
     * be checked for, nor stored in the cache.
     * 
     * @param boolean &$enabled Out parameter containing the current $enabled flag. The initial value of this will
     * be the result of DynamicCache's internal rules.
     */
    public function updateEnabled(&$enabled)
    {
    }
    
    /**
     * Alter the list of fragments (strings) that will be used to generate the cache key for this request.
     * Additional items can be added to (or altered/removed from) this list in order to create additional
     * segments within the cache.
     * 
     * @param array &$fragments Out parameter containing the list of strings that identify this cache element.
     * By default the fragments will contain the hostname (if segmentHostname is true) and the url.
     */
    public function updateCacheKeyFragments(array &$fragments)
    {
    }
}
