<?php
namespace PHPCraft\Section;

trait SectionWithGlobalActionsTrait {
    
    /**
     * Sets a section global action
     * @param array $action associative array with keys 'url' (complete url from site root), 'action' and 'label'
     **/
    public function setGlobalAction($action)
    {
        if(!isset($this->templateParameters['sectionGlobalActions'])) $this->templateParameters['sectionGlobalActions'] = array();
        $this->templateParameters['sectionGlobalActions'][] = $action;
    }
    
    /**
     * Sets section global actions
     * @param array associative array with keys 'url' (complete url from site root), 'action' and 'label'
     **/
    public function setGlobalActions($actions)
    {
        if(!isset($this->templateParameters['sectionGlobalActions'])) $this->templateParameters['sectionGlobalActions'] = array();
        foreach($actions as $action){
            $this->setGlobalAction($action);
        }
    }
}