<?php
namespace PHPCraft\Subject;

trait TitledSubjectTrait {

    /**
     * sets page title
     * @param string $subjectTitle
     */
    protected function setPageTitle($subjectTitle) {
        $this->templateParameters['page_title'] = 
            $subjectTitle
            . '::' .
            sprintf($this->translations[$this->application]['page_title'], $this->translations[$this->application]['application']);
    }    
}