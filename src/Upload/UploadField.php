<?php

namespace PHPCraft\Subject\Upload;

/**
 * Manages uploads using codeguy/upload  (https://github.com/codeguy/Upload.git)
 *
 * @author vuk <info@vuk.bg.it>
 */
class UploadField
{
    protected $field;
    protected $destination;
    /**
    * array[
    * type => as defined into Upload adapter
    * options => for the type, if any 
    * message => localized message for rule breaking warning
    **/
    protected $validationRules;
    
    /**
     * constructor
     *
     * @param string $field
     * @param string $destination path to destination directory
     * @param array $validationRules array of rules to validate uploaded file against
     **/
    public function __construct($field, $destination, $validationRules = null)
    {
        $this->field = $field;
        $this->destination = $destination;
        if(substr($this->destination, -1) != '/') {
            $this->destination .= '/';
        }
        $this->validationRules = $validationRules;
    }
}