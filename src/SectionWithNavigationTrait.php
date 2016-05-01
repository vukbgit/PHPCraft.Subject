<?php
namespace PHPCraft\Section;

trait SectionWithNavigationTrait {
    
    protected $sectionBaseUrl;
    /**
     * stores navigation structures as array
     * @param array $navigations
     */
    public function addNavigations($navigations) {
        $this->templateParameters['navigations'] = $navigations;
    }
    
    /**
     * sets current section base url
     * @param array $href
     */
    public function setSectionBaseUrl($url) {
        $this->sectionBaseUrl = $url;
        $this->templateParameters['sectionBaseUrl'] = $url;
    }
}