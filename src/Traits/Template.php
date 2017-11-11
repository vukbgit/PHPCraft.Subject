<?php
/**
 * template trait for a PHPCraft subject
 * traits optional automatic called methods:
 *      setTraitDependencies[trait-name](): calls $this->setTraitDependencies
 *      setTraitInjections[trait-name](): calls $this->setTraitInjections
 *      processRouteTrait[trait-name](): processes route
 *      processConfigurationTrait[trait-name](): processes configuration
 *      initTrait[trait-name](): performs any task needed by trait BEFORE subject action is performed
 * @author vuk <http://vuk.bg.it>
 */
 
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
     * Sets trait needed injections
     **/
    protected function setTraitInjectionsTemplate()
    {
        $this->setTraitInjections('Template', ['templateEngine']);
    }
    
    /**
     * Injects template engine instance
     * @param PHPCraft\Template\TemplateInterface $templateEngine template engine instance
     **/
    public function injectTemplateEngine(TemplateInterface $templateEngine)
    {
        $this->templateEngine = $templateEngine;
    }
    
    /**
     * Inits trait
     **/
    protected function initTraitTemplate()
    {
        $this->buildTemplateFunctions();
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
     * Sets common template parameters
     **/
    private function setCommonTemplateParameters()
    {
        $this->setTemplateParameter('application', APPLICATION);
        $this->setTemplateParameter('area', AREA);
        $this->setTemplateParameter('environment', ENVIRONMENT);
        $this->setTemplateParameter('subject', $this->name);
        $this->setTemplateParameter('language', LANGUAGE);
        $this->setTemplateParameter('configuration', $this->configuration);
        $this->setTemplateParameter('route', $this->route);
        $this->setTemplateParameter('translations', $this->translations);
        $this->setTemplateParameter('action', $this->action);
        $this->setTemplateParameter('ancestors', $this->ancestors);
        if($this->hasMessages && !empty($this->storedMessages)) {
            $this->setTemplateParameter('messages', $this->storedMessages);
        }
    }
    
    /**
     * Sets page title to be displayed into title HTML tag before 
     * @param string $title;
     **/
    public function setPageTitle($title)
    {
        $this->setTemplateParameter('pageTitle', $title);
    }
    
    /**
     * Builds functions used into templates
     **/
    protected function buildTemplateFunctions()
    {
        //path to area
        $this->templateEngine->addFunction('pathToArea', function ($language = false) {
            return implode('/', $this->buildPathToArea($language)) . '/';
        });
        //path to subject
        $this->templateEngine->addFunction('pathToSubject', function ($language = false) {
            return implode('/', $this->buildPathToSubject($language)) . '/';
        });
        //path to action
        $this->templateEngine->addFunction('pathToAction', function ($action, $configurationUrl = false, $primaryKeyValue = false) {
            return $this->buildPathToAction($action, $configurationUrl, $primaryKeyValue);
        });
        //path to ancestor
        $this->templateEngine->addFunction('pathToAncestor', function ($ancestor) {
            return implode('/', $this->buildPathToAncestor($ancestor));
        });
        //authentication functions
        if($this->hasAuthentication) {
            $this->templateEngine->addFunction('hasPermission', function ($subject, $permission) {
                return $this->hasPermission($subject, $permission);
            });
            $this->templateEngine->addFunction('hasSubjectPermission', function ($subject) {
                return $this->hasSubjectPermission($subject);
            });
        }
    }
    
    /**
     * Renders template and writes output to HTTP stream
     * @param string $path;
     * @return string HTML content
     **/
    protected function renderTemplate($path = false)
    {
        if(!$this->templateEngine) {
            throw new \Exception('template engine not injected');
        }
        if(!$path) {
            $path = sprintf('%s/%s/%s', AREA, $this->name, $this->action);
        }
        $this->setCommonTemplateParameters();
        $html = $this->templateEngine->render($path, $this->templateParameters);
        $this->httpStream->write($html);
        return $html;
    }
}