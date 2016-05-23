<?php
namespace PHPCraft\Subject;

trait SubjectWithNavigationTrait {
    
    protected $subjectBaseUrl;
    /**
     * stores navigation structures as array
     * @param array $navigations
     */
    public function addNavigations($navigations) {
        $this->templateParameters['navigations'] = $navigations;
    }
    
    /**
     * sets current subject base url
     * @param array $href
     */
    public function setSubjectBaseUrl($url) {
        $this->subjectBaseUrl = $url;
        $this->templateParameters['subjectBaseUrl'] = $url;
    }
}