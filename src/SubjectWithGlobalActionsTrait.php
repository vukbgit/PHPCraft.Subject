<?php
namespace PHPCraft\Subject;

trait SubjectWithGlobalActionsTrait {
    
    /**
     * Sets a subject global action
     * @param array $action associative array with keys 'url' (complete url from site root), 'action' and 'label'
     **/
    public function setGlobalAction($action)
    {
        if(!isset($this->templateParameters['subjectGlobalActions'])) $this->templateParameters['subjectGlobalActions'] = array();
        $this->templateParameters['subjectGlobalActions'][] = $action;
    }
    
    /**
     * Sets subject global actions
     * @param array associative array with keys 'url' (complete url from site root), 'action' and 'label'
     **/
    public function setGlobalActions($actions)
    {
        if(!isset($this->templateParameters['subjectGlobalActions'])) $this->templateParameters['subjectGlobalActions'] = array();
        foreach($actions as $action){
            $this->setGlobalAction($action);
        }
    }
}