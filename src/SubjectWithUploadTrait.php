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
        //add fields
        $this->templateParameters['uploadPreviewsTemplates'] = array();
        foreach((array) $this->uploadFieldsDefinitions as $field => $uploadField) {
            $validations = isset($uploadField['validations']) ? $uploadField['validations'] : null;
            $previewTemplate = isset($uploadField['previewTemplate']) ? $uploadField['previewTemplate'] : null;
            $this->addUploadField($field, $uploadField['outputs'], $validations, $previewTemplate);
        }
        //add PHP ini upload_max_filesize value
        $this->templateParameters['uploadMaxFilesize'] = ini_get('upload_max_filesize');
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
     * Displays insert form
     */
    protected function execInsertForm($updateGlobalAction = array())
    {
        $this->addTranslations('form', sprintf('private/global/locales/%s/form.ini', $this->language));
        $this->setUploadFieldsDefinitions();
        $this->addUploadFields();
        parent::execInsertForm();
    }
    
    /**
     * Displays update form
     */
    protected function execUpdateForm($updateGlobalAction = array())
    {
        $this->addTranslations('form', sprintf('private/global/locales/%s/form.ini', $this->language));
        $this->setUploadFieldsDefinitions();
        $this->addUploadFields();
        parent::execUpdateForm();
    }
    
     /**
     * Execs upload action
     */
    protected function execUpload()
    {
        $this->addTranslations('form', sprintf('private/global/locales/%s/form.ini', $this->language));
        $this->setUploadFieldsDefinitions();
        $this->addUploadFields();
        $this->handleUpload();
        echo $this->outputUploadOutcome();
    }
    
    /**
     * Processes save input before save query, to be overridden by derived class in case of input processing needed
     * @param array $input
     * @return array $input
     */
    protected function processSaveInput($input)
    {
        $this->addTranslations('form', sprintf('private/global/locales/%s/form.ini', $this->language));
        $this->setUploadFieldsDefinitions();
        if($this->action == 'update') {
            $this->checkUploadOutputsToDelete();
        }
        return parent::processSaveInput($input);
    }
    
    /**
     * Displays delete form
     */
    protected function execDeleteForm()
    {
        $globalAction = [
            'url' => false,
            'action' => 'deleteForm',
            'label' => $this->translations[$this->area]['operations']['do_delete'] . ' ' . $this->translations[$this->subject]['singular']
        ];
        $this->setGlobalAction($globalAction);
        parent::execDeleteForm();
    }
    
    /**
     * deletes record
     * @param string $redirectAction
     */
    protected function execDelete($redirectAction = null)
    {
        $this->addTranslations('form', sprintf('private/global/locales/%s/form.ini', $this->language));
        $this->setUploadFieldsDefinitions();
        $this->checkUploadOutputsToDelete();
        parent::execDelete();
    }
    
    /**
     * Outputs to browser upload outcome in json format for ajax calls benefit
     * @return string json code
     **/
    protected function checkUploadOutputsToDelete()
    {
        //get record
        $recordId = filter_input_array(INPUT_POST, $this->postedFieldsDefinition)[$this->primaryKey];
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
                        if(is_file($output->path)) {
                            unlink($output->path);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * extracts from a record field, for one upload field, one output paths
     * @param object $record
     * @param string $field
     * @param array $outputs
     */
    public function extractFieldOutputs(&$record, $field, $outputs)
    {
        if(isset($record->{$field}) && $record->{$field} && $record->{$field} != '[]' && $record->{$field} != '{}') {
            //init files container
            if(!isset($record->files)) {
                $record->files = new \stdclass;
            }
            //init fields container
            if(!isset($record->files->{$field})) {
                $record->files->{$field} = array();
            }
            //decode image objects
            $imageObjects = json_decode($record->{$field});
            //loop saved images
            foreach($imageObjects as $imageObject) {
                //output container for current saved file
                $outputsFiles = new \stdClass;
                //loop outputs to extract
                foreach($outputs as $output) {
                    //check if output is saved
                    if(isset($imageObject->outputs->{$output})) {
                        //store output path
                        $outputsFiles->{$output} = $imageObject->outputs->{$output}->path;
                    }
                }
                //store extracted outputs paths
                $record->files->{$field}[] = $outputsFiles;
            }
        }
    }
    
    /**
     * extracts for a record, for one or more upload fields, one or more outputs paths
     * @param object $record
     * @param array $fieldsOutputs: indexes are fields names, elements are outputs names
     */
    protected function extractFieldsOutputs(&$record, $fieldsOutputs)
    {
        foreach($fieldsOutputs as $field => $outputs) {
            $this->extractFieldOutputs($record, $field, $outputs);
        }
    }
    
    /**
     * extracts for a list of records, for one or more upload fields, one or more outputs paths
     * @param object $record
     * @param array $fieldsOutputs: indexes are fields names, elements are outputs names
     */
    protected function extractFieldsOutputsList(&$records, $fieldsOutputs)
    {
        foreach($records as $record) {
            $this->extractFieldsOutputs($record, $fieldsOutputs);
        }
    }
}