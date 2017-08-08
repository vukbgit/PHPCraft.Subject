<?php
/**
 * cookies trait for a PHPCraft subject
 * traits optional automatic called methods:
 *      setTraitDependencies[trait-name](): calls $this->setTraitDependencies
 *      setTraitInjections[trait-name](): calls $this->setTraitInjections
 *      processRouteTrait[trait-name](): processes route
 *      processConfigurationTrait[trait-name](): processes configuration
 *      initTrait[trait-name](): performs any task needed by trait BEFORE subject action is performed
 * @author vuk <http://vuk.bg.it>
 */
 
namespace PHPCraft\Subject\Traits;
use PHPCraft\Cookie\CookieInterface;

trait Cookies{
    
    /**
    * included trait flag 
    **/
    protected $hasCookies = true;
    
    /**
    * Cookies manager instance
    **/
    protected $cookies;
    
    /**
     * Sets trait needed injections
     **/
    protected function setTraitInjectionsCookies()
    {
        $this->setTraitInjections('Cookies', ['cookies']);
    }
    
    /**
     * Injects cookies manager instance
     * @param PHPCraft\Cookie\CookieInterface $cookies cookies manager instance
     **/
    public function injectCookies(CookieInterface $cookies)
    {
        $this->cookies = $cookies;
    }
}