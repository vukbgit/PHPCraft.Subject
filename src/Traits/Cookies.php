<?php
namespace PHPCraft\Subject\Traits;
use PHPCraft\Cookie\CookieInterface;

trait Cookies{
    
    /**
    * included trait flag 
    **/
    protected $hasCookies = true;
    
    /**
    * Query builder instance
    **/
    protected $cookies;
    
    /**
     * Injects cookies manager instance
     * @param PHPCraft\Cookie\CookieInterface $cookies cookies manager instance
     **/
    public function injectCookies(CookieInterface $cookies)
    {
        $this->cookies = $cookies;
    }
}