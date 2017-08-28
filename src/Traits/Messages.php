<?php
/**
 * messages trait for a PHPCraft subject
 * traits optional automatic called methods:
 *      setTraitDependencies[trait-name](): calls $this->setTraitDependencies
 *      setTraitInjections[trait-name](): calls $this->setTraitInjections
 *      processRouteTrait[trait-name](): processes route
 *      processConfigurationTrait[trait-name](): processes configuration
 *      initTrait[trait-name](): performs any task needed by trait BEFORE subject action is performed
 * @author vuk <http://vuk.bg.it>
 */
 
namespace PHPCraft\Subject\Traits;
use PHPCraft\Message\Message;

trait Messages{
    
    /**
    * included trait flag 
    **/
    protected $hasMessages = true;
    
    /**
    * Messages manager instance
    **/
    protected $messages;
    
    /**
    * Stored messages
    **/
    protected $storedMessages;
    
    /**
     * Sets trait dependencies from other traits
     **/
    public function setTraitDependenciesMessages()
    {
        $this->setTraitDependencies('Messages', ['Cookies']);
    }
    
    /**
     * Sets trait needed injections
     **/
    protected function setTraitInjectionsMessages()
    {
        $this->setTraitInjections('Messages', ['messages']);
    }
    
    /**
     * Injects messages manager instance
     * @param PHPCraft\Message\Message $messages messages manager instance
     **/
    public function injectMessages(Message $messages)
    {
        $this->messages = $messages;
        $this->messages->setCookie($this->cookies);
    }
    
    /**
     * Inits trait
     **/
    protected function initTraitMessages()
    {
        $this->getStoredMessages();
    }
    
    /**
     * Gets and clear stored messages
     **/
    public function getStoredMessages()
    {
        $this->storedMessages = $this->messages->get('cookies');
    }
    
    /**
     * Adds a stored message at runtime
     **/
    public function addStoredMessage($category, $message)
    {
        if(!isset($this->storedMessages[$category])) {
            $this->storedMessages[$category] = [];
        }
        $this->storedMessages[$category][] = $message;
    }
}