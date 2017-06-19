<?php
namespace PHPCraft\Subject\Traits;
use PHPCraft\Message\Message;

trait Cookies{
    
    use Cookies;
    
    /**
    * included trait flag 
    **/
    protected $hasMessages = true;
    
    /**
    * Query builder instance
    **/
    protected $messages;
    
    /**
     * Injects messages manager instance
     * @param PHPCraft\Message\Message $messages messages manager instance
     **/
    public function injectMessages(Message $messages)
    {
        $this->messages = $messages;
    }
}