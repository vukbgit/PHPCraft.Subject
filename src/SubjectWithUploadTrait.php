<?php
namespace PHPCraft\Subject;

trait SubjectWithUploadTrait {
    
    protected $uploader;
    protected $uploadOk;
    protected $messages;
    protected $uploadFields;
    protected $uploadedField;
    
    /**
     * Injects the uploader instance
     * @param $uploader instance of upload adapter
     **/
    public function injectUploader($uploader)
    {
        $this->uploader = $uploader;
        $this->uploadFields = array();
        $this->messages = array();
    }
    
    /**
     * Adds an uploadField definition
     * @param string $field
     * @param array $outputs definitions for uploaded file different outputs
     * @param array $validations rules to validate uploaded file against
     * @param array $previewTemplate html template to be shown into preview
     **/
    protected function addUploadField($fieldName, $outputs, $validations = null, $previewTemplate = null)
    {
        $this->uploadFields[$fieldName] = new Upload\Field($fieldName, $validations, $previewTemplate);
        foreach($outputs as $outputName => $output) {
            $this->uploadFields[$fieldName]->addOutput($outputName, $output['destination']);
        }
    }
    
    /**
     * Adds one or more uploadField definitions
     * @param array $uploadFields indexed by field names
     **/
    protected function addUploadFields($uploadFields)
    {
        foreach((array) $uploadFields as $field => $uploadField) {
            $validations = isset($uploadField['validations']) ? $uploadField['validations'] : null;
            $previewTemplate = isset($uploadField['previewTemplate']) ? $uploadField['previewTemplate'] : null;
            $this->addUploadField($field, $uploadField['outputs'], $validations, $previewTemplate);
        }
    }
    
    /**
     * Executes the upload action
     **/
    protected function handleUpload()
    {
        //check if any uploaded file for a defined field
        $this->uploadOk = $this->checkUpload();
        if(!$this->uploadOk) {
            return;
        }
        //exec upload
        $this->uploadOk = $this->uploadFields[$this->uploadedField]->handleUpload($this->uploader);
    }
    
    /**
     * Checks whether an upload has been tried
     * @return boolean, true on success false on failure
     **/
    protected function checkUpload()
    {
        //check for which field the file has been sent
        $this->uploadedField = filter_input(INPUT_POST, 'uploadedField', FILTER_SANITIZE_STRING);
        //no uploaded field
        if(!$this->uploadedField) {
            $this->messages[] = 'upload file with name "uploadedField" must be set into upload form for upload to work';
            return false;
        }
        //no upload field defined for uploaded field
        if(!isset($this->uploadFields[$this->uploadedField])) {
            $this->messages[] = sprintf('No definition set for POST uploadField field "%s"', $this->uploadedField);
            return false;
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
            if($this->uploader->getMessages()) {
                $this->messages[] = implode('\n', $this->uploader->getMessages());
            }
            $json->error = implode('\n', $this->messages);
        } else {
            // generated file(s) previews
            $json->initialPreview = $this->uploadFields[$this->uploadedField]->getPreviews();
            // generated file(s) paths
            $json->outputs = $this->uploadFields[$this->uploadedField]->getOutputsFiles();
        }
        /*$json->initialPreviewConfig = [
            [
                'type' => 'image',
                'caption' => 'CAPTION', 
                'width' => '120px',
                'size' => '100',
                'frameClass' => 'my-awesome-frameClass'
                //url: 'http://localhost/avatar/delete', // server delete action 
                //key: 100, 
                //extra: {id: 100}
            ]
        ];*/
        return json_encode($json);
    }
}