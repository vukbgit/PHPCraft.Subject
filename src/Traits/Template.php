<?php
namespace PHPCraft\Subject\Traits;

use PHPCraft\Template\TemplateInterface;

trait Template{
    
    /**
    * included trait flag 
    **/
    protected $hasTemplate = true;
    
    /**
    * Template engine instance
    **/
    protected $templateEngine;
    
    /**
    * Parameters passed to template
    **/
    protected $templateParameters = [];
    
    /**
     * Injects template engine instance
     * @param PHPCraft\Template\TemplateInterface $templateEngine template engine instance
     **/
    public function injectTemplateEngine(TemplateInterface $templateEngine)
    {
        $this->templateEngine = $templateEngine;
    }
    
    /**
     * Sets a template parameter
     * @param string $key;
     * @param mixed $value;
     **/
    public function setTemplateParameter($key, $value)
    {
        $this->templateParameters[$key] = $value;
    }
    
    /**
     * Renders template
     * @param string $path;
     **/
    protected function renderTemplate($path = false)
    {
        if(!$this->templateEngine) {
            throw new \Exception('template engine not injected');
        }
        if(!$path) {
            $path = sprintf('%s/%s/%s', AREA, $this->subject, $this->action);
        }
        $this->templateParameters['translations'] = $this->translations;
        $html = $this->templateEngine->render($path, $this->templateParameters);
        $this->httpStream->write($html);
    }
}