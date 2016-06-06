<?php

namespace PHPCraft\Subject\Upload;

/**
 * Manages uploads using codeguy/upload  (https://github.com/codeguy/Upload.git)
 *
 * @author vuk <info@vuk.bg.it>
 */
class Field
{
    protected $field;
    /**
    * array[
    * type => as defined into Upload adapter
    * options => for the type, if any 
    * message => localized message for rule breaking warning
    **/
    protected $validationRules;
    protected $outputs;
    
    /**
     * constructor
     *
     * @param string $field
     * @param array $validationRules array of rules to validate uploaded file against
     **/
    public function __construct($field, $validationRules = null)
    {
        $this->field = $field;
        $this->validationRules = $validationRules;
    }
}