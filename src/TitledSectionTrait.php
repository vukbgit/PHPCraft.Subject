<?php
namespace PHPCraft\Section;

trait TitledSectionTrait {

    /**
     * sets page title
     * @param string $sectionTitle
     */
    protected function setPageTitle($sectionTitle) {
        $this->templateParameters['page_title'] = 
            $sectionTitle
            . '::' .
            sprintf($this->translations[$this->application]['page_title'], $this->translations[$this->application]['application']);
    }    
}