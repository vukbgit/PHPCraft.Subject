<?php
namespace PHPCraft\Subject;

trait SubjectWithUploadTrait {
    
    protected $uploader;
    /*protected $fieldsDefinitions;
    protected $field;
    protected $uploadOk;*/
    protected uploadfields;
    
    /**
     * Injects the uploader instance
     * @param string $path
     **/
    public function injectUploader($uploader)
    {
        $this->uploader = $uploader;
        $this->uploadFields = array();
    }
    
    /**
     * Executes the upload action
     * @param array $fieldsDefinitions, an array of arrays, indexed by field name, each representig a field definition with:
     *          validationRules => array of rules to validate uploaded file against (see handleUpload())
     *          destination => formatted string with containing path from application-root with ending slash and without filename
     **/
    protected function handleUploadFields($fieldsDefinitions)
    {
        $this->fieldsDefinitions = $fieldsDefinitions;
        //check for which field the file has been sent
        $this->field = filter_input(INPUT_POST, 'uploadTargetField', FILTER_SANITIZE_STRING);
        if(!$this->field) {
            throw new \Exception('POST uploadTargetField field must be set for upload to work');
        }
        $this->handleUpload();
    }
    
    /**
     * Executes the upload action
     **/
    protected function handleUpload()
    {
        // set field
        $this->uploader->setfield($this->field);
        // set container
        $this->uploader->setDestination($this->fieldsDefinitions[$this->field]['destination']);
        // set rules
        foreach($this->fieldsDefinitions[$this->field]['validationRules'] as $rule) {
            $options = isset($rule['options']) ? $rule['options'] : null;
            $message = isset($rule['message']) ? $rule['message'] : null;
            $this->uploader->addValidationRule($rule['type'], $options, $message);
        }
        // upload
        $this->uploadOk = $this->uploader->process();
        // success
        if($this->uploadOk) {
            $this->uploader->close();
        }
    }
    
    /**
     * Outputs to browser upload outcome in json format for ajax calls benefit
     * @return string json code
     **/
    protected function outputUploadOutcome()
    {
        $json = new \stdClass();
        // errors
        if(!$this->uploadOk) {
            $json->error = implode('\n', $this->uploader->getMessages());
        }
        // uploaded file
        $json->initialPreview = [
            '<img class="file-preview-image" src="' . $this->fieldsDefinitions[$this->field]['destination'] . '/' . $this->uploader->getUploadedFileInfo()['name'] . '">'
        ];
        return json_encode($json);
    }
    
    /**
     * Adds an uploadField definition
     * @param string $field
     * @param array $validationRules array of rules to validate uploaded file against (see handleUpload())
     **/
    protected function addUploadField($name, $validationRules)
    {
        $this->uploadFields[] = [
            'name' => $name,
            'validationRules' => $validationRules
        ];
    }
    
    /**
     * Adds one or more uploadField definitions
     * @return array indexed by field names
     **/
    protected function addUploadFields($uploadFields)
    {
        foreach((array) $uploadFields as $field => $uploadField) {
            $this->addUploadField($field, $uploadField['validationRules']);
        }
        r($this->uploadFields);
    }
}