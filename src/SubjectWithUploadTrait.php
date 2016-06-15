<?php
namespace PHPCraft\Subject;

trait SubjectWithUploadTrait {
    
    protected $uploader;
    protected $uploadOk;
    protected $messages;
    protected $uploadFieldsDefinitions;
    protected $uploadFields;
    protected $uploadedField;
    protected $image;
    
    /**
     * Injects the uploader instance
     * @param $uploader instance of upload adapter implementing PHPCraft\Upload\UploadInterface
     **/
    public function injectUploader(\PHPCraft\Upload\UploadInterface $uploader)
    {
        $this->uploader = $uploader;
        $this->uploadFields = array();
        $this->messages = array();
    }
    
    /**
     * Injects the image instance
     * @param $image instance of image adapter implementing PHPCraft\Image\ImageInterface
     **/
    public function injectImage(\PHPCraft\Image\ImageInterface $image)
    {
        $this->image = $image;
    }
    
    /**
     * Sets uploadField definitions
     * @param array $uploadFieldsDefinitions indexed by field names
     **/
    public function setUploadFieldsDefinitions()
    {
        throw new \Exception(sprintf('%s class must implement setUploadFieldsDefinitions() method that defines fields', $this->subject));
        $uploadFieldsDefinitions = [
            'field-name' => [
                'validations' => [
                    [
                        'type' => 'upload-type', // see upload class
                        'options' => [
                            'allowed' => ['extension'],
                        ],
                        'message' => 'translation-from-translations-array'
                    ]
                    
                ],
                'outputs' => [
                    'output-name' => [
                        'destination' =>  'path-for-saving/',
                        'processor' => 'method-name' //called over output after upload
                    ]
                ],
                'previewTemplate' => 'html-code-with-placeholders-{{field-name.[path|name]}}'
            ]
        ];
        $this->uploadFieldsDefinitions = $uploadFieldsDefinitions;
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
        //validation messages
        if($validations) {
            foreach($validations as $validation) {
                if(isset($validation['messageKey'])) {
                    $validation['message'] = $this->translations['form']['upload'][$validation['messageKey']];
                }
            }
        }
        $this->uploadFields[$fieldName] = new Upload\Field($fieldName, $validations, $previewTemplate);
        foreach($outputs as $outputName => $output) {
            $this->uploadFields[$fieldName]->addOutput($outputName, $output['destination']);
        }
        $this->templateParameters['uploadPreviewsTemplates'][$fieldName] = $previewTemplate;
    }
    
    /**
     * Adds uploadField definitions
     **/
    protected function addUploadFields()
    {
        $this->templateParameters['uploadPreviewsTemplates'] = array();
        foreach((array) $this->uploadFieldsDefinitions as $field => $uploadField) {
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
        $this->uploadedField = array_keys($_FILES)[0];
        //check if any uploaded file for a defined field
        $this->uploadOk = $this->checkUpload();
        if(!$this->uploadOk) {
            return;
        }
        //exec upload
        $this->uploadOk = $this->uploadFields[$this->uploadedField]->handleUpload($this->uploader);
        //outputs post processing
        $outputs = $this->uploadFields[$this->uploadedField]->getOutputsFiles();
        foreach($this->uploadFieldsDefinitions[$this->uploadedField]['outputs'] as $output => $outputDefinition) {
            if(isset($outputDefinition['processor'])) {
                $this->{$outputDefinition['processor']}($outputs[$output]['path']);
            }
        }
        
    }
    
    /**
     * Checks whether an upload has been tried
     * @return boolean, true on success false on failure
     **/
    protected function checkUpload()
    {
        //no upload field defined for uploaded field
        if(!isset($this->uploadFields[$this->uploadedField])) {
            $this->messages[] = sprintf('No definition set for POST uploadField field "%s"', $this->uploadedField);
            return false;
        }
        //no processing method implemented for any of outputs
        foreach($this->uploadFieldsDefinitions[$this->uploadedField]['outputs'] as $output => $outputDefinition) {
            if(isset($outputDefinition['processor'])) {
                if(!method_exists($this, $outputDefinition['processor'])) {
                    $this->messages[] = sprintf('Class <b>%s</b> must define method <b>%s</b> to process output <b>%s</b> of upload field <b>%s</b>', $this->subject, $outputDefinition['processor'], $output, $this->uploadedField);
                    return false;
                }
            }
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
            $this->httpResponse = $this->httpResponse->withStatus('412', $json->error);
        } else {
            //input file informations
            $json->input = [
                'name' => $this->uploadFields[$this->uploadedField]->getInputFile(),
                'hash' => $this->uploadFields[$this->uploadedField]->getInputHash()
            ];
            // generated file(s) paths
            $json->outputs = $this->uploadFields[$this->uploadedField]->getOutputsFiles();
        }
        return json_encode($json);
    }
    
    /**
     * Riminder to implement execUpdateForm
     * @throws Exception always, unless overridden
     **/
    protected function execUpdateForm($updateGlobalAction = array())
    {
        throw new \Exception(sprintf('%s class must implement execUpdateForm() method that calls setUploadFieldsDefinitions and eventually includes private/global/locales/selected-language/form.ini if necessary', $this->subject));
    }
    
     /**
     * Riminder to override execUpload
     * @throws Exception always, unless overridden
     **/
    protected function execUpload()
    {
        throw new \Exception(sprintf('%s class must implement execUpload()  method that calls setUploadFieldsDefinitions, eventually includes private/global/locales/selected-language/form.ini if necessary and calls handleUpload()', $this->subject));
    }
    
    /**
     * Outputs to browser upload outcome in json format for ajax calls benefit
     * @return string json code
     **/
    protected function checkUploadOutputsToDelete()
    {
        //get record
        $recordId = filter_input(INPUT_POST, $this->primaryKey, FILTER_VALIDATE_INT);
        $this->queryBuilder->table($this->dbView);
        $this->queryBuilder->where($this->primaryKey,$recordId);
        $record = $this->queryBuilder->get()[0];
        $fields = array_keys($this->uploadFieldsDefinitions);
        //loop defined upload fields
        foreach($fields as $field) {
            //get record field value
            $fieldValue = json_decode($record->$field);
            //skip empty field
            if(!$fieldValue) {
                continue;
            }
            //get posted value
            if($this->action == 'update') {
                $postedInputs = array_keys(get_object_vars(json_decode($_POST[$field])));
            }
            //loop inputs
            foreach((array) $fieldValue as $hash => $input) {
                if($this->action == 'delete' || !in_array($hash, $postedInputs)) {
                    //loop outputs
                    foreach($input->outputs as $output) {
                        unlink($output->path);
                    }
                }
            }
        }
    }
}