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
        //send user data to template
        if($this->hasAuthentication && $this->isAuthenticated()) {
            $this->setTemplateParameter('userData', $this->getUserData());
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
    protected function buildTemplateFunctions($onlyUnregistered = false)
    {
        if(!$onlyUnregistered) {
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
            //custom path to action
            $this->templateEngine->addFunction('customActionUrl', function ($record, $url) {
                return $this->buildCustomActionUrl($record, $url);
            });
        }
        //crud functions
        if(($this->hasCRUD && !$onlyUnregistered) || ($onlyUnregistered && !$this->hasCRUD)) {
            //get primary key value(s)
            $this->templateEngine->addFunction('extractPrimaryKeyValue', function ($record, $returnAs) {
                return $this->extractPrimaryKeyValue($record, $returnAs);
            });
        }
        //authentication functions
        if(($this->hasAuthentication && !$onlyUnregistered) || ($onlyUnregistered && !$this->hasAuthentication)) {
            $this->templateEngine->addFunction('hasPermission', function ($subject, $permission) {
                return $this->hasPermission($subject, $permission);
            });
            $this->templateEngine->addFunction('hasSubjectPermission', function ($subject) {
                return $this->hasSubjectPermission($subject);
            });
        }
    }

    /**
     * Builds a custom path to an action using a record and a url with fields related placeholders
     * @param object $record
     * @param string $url: with field palceholders in the form of {field-name}
     * @return string HTML content
     **/
    protected function buildCustomActionUrl($record, $url)
    {
        $pattern = '/\{([a-zA-z_]+)\}/';
        $resultUrl = preg_replace_callback(
            $pattern,
            function($matches) use($record){
                $fieldName = $matches[1];
                if(isset($record->$fieldName)) {
                    return $record->$fieldName;
                }

            },
            $url);
        return $resultUrl;
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
            $path = sprintf('%s/%s', $this->buildPrivatePathToSubject(), $this->action);
        }
        $this->setCommonTemplateParameters();
        $html = $this->templateEngine->render($path, $this->templateParameters);
        $this->httpStream->write($html);
        return $html;
    }
}
