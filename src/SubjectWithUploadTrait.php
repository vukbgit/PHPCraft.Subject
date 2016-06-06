<?php
namespace PHPCraft\Subject;

trait SubjectWithUploadTrait {
    
    protected $uploader;
    /*protected $fieldsDefinitions;
    
    protected $uploadOk;*/
    protected $uploadFields;
    protected $uploadedField;
    
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
     **/
    protected function handleUpload()
    {
        //check if any upload
        $this->checkUpload();
        // set field
        $this->uploader->setfield($this->uploadedField);
        // set container
        $this->uploader->setDestination($this->uploadedFieldsDefinitions[$this->uploadedField]['destination']);
        // set rules
        foreach($this->uploadedFieldsDefinitions[$this->uploadedField]['validationRules'] as $rule) {
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
     * Checks whether an upload has been tried
     * @throws Exception if no field with 'uploadedField' name has been sent
     * @return boolean, true on success
     **/
    protected function checkUpload()
    {
        //check for which field the file has been sent
        $this->uploadedField = filter_input(INPUT_POST, 'uploadedField', FILTER_SANITIZE_STRING);
        //error
        if(!$this->uploadedField) {
            throw new \Exception('POST uploadedField field must be set for upload to work');
        }
        return true;
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
            '<img class="file-preview-image" src="' . $this->uploadedFieldsDefinitions[$this->uploadedField]['destination'] . '/' . $this->uploader->getUploadedFileInfo()['name'] . '">'
        ];
        return json_encode($json);
    }
    
    /**
     * Adds an uploadField definition
     * @param string $field
     * @param string $destination path to destination directory
     * @param array $validationRules array of rules to validate uploaded file against (see handleUpload())
     **/
    protected function addUploadField($name, $destination, $validationRules)
    {
        $this->uploadFields[] = new Upload\UploadField($name, $destination, $validationRules);
    }
    
    /**
     * Adds one or more uploadField definitions
     * @return array indexed by field names
     **/
    protected function addUploadFields($uploadFields)
    {
        foreach((array) $uploadFields as $field => $uploadField) {
            $this->addUploadField($field, $uploadField['destination'], $uploadField['validationRules']);
        }
    }
}