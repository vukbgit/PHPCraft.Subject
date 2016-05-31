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
    
    /**
     * sets current subject base path, the part of URL path from application-root that all of subject rules share
     * @param array $href
     */
    public function setSubjectBasePath($path) {
        $this->subjectBasePath = $path;
        $this->templateParameters['subjectBasePath'] = $path;
    }
}