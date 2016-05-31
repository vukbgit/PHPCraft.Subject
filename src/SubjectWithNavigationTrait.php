<?php
namespace PHPCraft\Subject;

trait SubjectWithNavigationTrait {
    
    protected $subjectBasePath;
    /**
     * stores navigation structures as array
     * @param array $navigations
     */
    public function addNavigations($navigations) {
        $this->templateParameters['navigations'] = $navigations;
    }    
}