<?php
/**
 * upload trait for a PHPCraft subject
 * traits optional automatic called methods:
 *      setTraitDependencies[trait-name](): calls $this->setTraitDependencies
 *      setTraitInjections[trait-name](): calls $this->setTraitInjections
 *      processRouteTrait[trait-name](): processes route
 *      processConfigurationTrait[trait-name](): processes configuration
 *      initTrait[trait-name](): performs any task needed by trait BEFORE subject action is performed
 * @author vuk <http://vuk.bg.it>
 */
 
namespace PHPCraft\Subject\Traits;
use PHPCraft\Upload\UploadInterface;

trait Upload{
    
    /**
    * included trait flag 
    **/
    protected $hasUpload = true;
    
    /**
    * Uploader instance
    **/
    protected $uploader;
    
    /**
    * Fields definitions
    **/
    protected $uploadFieldsDefinitions;
    
    /**
    * Fields instances
    **/
    protected $uploadFields;
    
    /**
    * Upload outcome flag
    **/
    protected $uploadOk = true;
    
    /**
    * Messages
    **/
    protected $uploadMessages = [];
    
    /**
     * Sets trait dependencies from other traits
     **/
    public function setTraitDependenciesUpload()
    {
        $this->setTraitDependencies('Upload', ['Database', 'Template']);
    }
    
    /**
     * Sets trait needed injections
     **/
    protected function setTraitInjectionsUpload()
    {
        $this->setTraitInjections('Upload', ['uploader']);
    }
    
    /**
     * Inits trait
     **/
    protected function initTraitUpload()
    {
        //files purge, in case of CRUD trait and update or delete action
        if($this->hasCRUD && in_array($this->action, ['update','delete'])) {
            $this->purgeFiles();
        }
        //template parameters, for all actions except upload that is ajax called
        if($this->action != 'upload') {
            //upload_max_filesize
            $this->setTemplateParameter('uploadMaxFilesize', ini_get('upload_max_filesize'));
            //fields preview templates
            $uploadPreviewsTemplates = [];
            foreach($this->uploadFieldsDefinitions as $field => $uploadFieldsDefinition) {
                $uploadPreviewsTemplates[$field] = $uploadFieldsDefinition['previewTemplate'];
            }
            $this->setTemplateParameter('uploadPreviewsTemplates', $uploadPreviewsTemplates);
        }
    }
    
    /**
     * Processes configuration
     * @param array $configuration
     **/
    protected function processConfigurationTraitUpload(&$configuration)
    {
        //check parameters
        if(!isset($configuration['subjects'][$this->name]['Upload']) || empty($configuration['subjects'][$this->name]['Upload'])) {
            throw new \Exception(sprintf('missing Upload fields definitions into %s subject configuration', $this->name));
        } else {
            $this->uploadFieldsDefinitions = $configuration['subjects'][$this->name]['Upload'];
        }
    }
    
    /**
     * Injects the uploader instance
     * @param $uploader instance of upload adapter implementing PHPCraft\Upload\UploadInterface
     **/
    public function injectUploader(UploadInterface $uploader)
    {
        $this->uploader = $uploader;
    }
    
    /**
     * Execs upload action
     */
    protected function execUpload()
    {
        $this->loadTranslations('form', sprintf('private/global/locales/%s/form.ini', LANGUAGE));
        //fields  instances
        foreach($this->uploadFieldsDefinitions as $fieldName => $definition) {
            $validations = isset($definition['validations']) ? $definition['validations'] : null;
            $previewTemplate = isset($definition['previewTemplate']) ? $definition['previewTemplate'] : null;
            $this->uploadFields[$fieldName] = new Upload\Field($fieldName, $validations, $previewTemplate);
            foreach($definition['outputs'] as $outputName => $output) {
                $this->uploadFields[$fieldName]->addOutput($outputName, $output['destination']);
            }
        }
        $this->handleUpload();
        echo $this->outputUploadOutcome();
    }
    
    /**
     * Checks whether an upload has been tried
     * @return boolean, true on success false on failure
     **/
    protected function checkUpload()
    {
        //no upload field defined for uploaded field
        if(!isset($this->uploadFields[$this->uploadedField])) {
            $this->uploadMessages[] = sprintf('No definition set for POST uploadField field "%s"', $this->uploadedField);
            return false;
        }
        //no processing method implemented for any of outputs
        foreach($this->uploadFieldsDefinitions[$this->uploadedField]['outputs'] as $output => $outputDefinition) {
            if(isset($outputDefinition['processor'])) {
                if(!method_exists($this, $outputDefinition['processor'])) {
                    $this->uploadMessages[] = sprintf('Class <b>%s</b> must define method <b>%s</b> to process output <b>%s</b> of upload field <b>%s</b>', $this->name, $outputDefinition['processor'], $output, $this->uploadedField);
                    return false;
                }
            }
        }
        return true;
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
        if(!$this->uploadOk) {
            return;
        }
        //outputs post processing
        $outputs = $this->uploadFields[$this->uploadedField]->getOutputsFiles();
        foreach($this->uploadFieldsDefinitions[$this->uploadedField]['outputs'] as $output => $outputDefinition) {
            if(isset($outputDefinition['processor'])) {
                $this->{$outputDefinition['processor']}($outputs[$output]['path']);
            }
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
            if($this->uploader->getMessages()) {
                $this->uploadMessages[] = implode('\n', $this->uploader->getMessages());
            }
            $json->error = implode('\n', $this->uploadMessages);
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
     * Checks in case of CRUD update or delete if any file is to be deleted
     **/
    protected function purgeFiles()
    {
        //get record
        //validate and extract input
        $input = $this->processSaveInput(filter_input_array(INPUT_POST, $this->configuration['subjects'][$this->name]['CRUD']['inputFields']));
        //extract primary key value
        $primaryKeyValue = $this->extractPrimaryKeyValue($input, 'a');
        $record = $this->getByPrimaryKey($primaryKeyValue);
        $fields = array_keys($this->uploadFieldsDefinitions);
        //loop defined upload fields
        foreach($fields as $field) {
            //skip other subject field (in case this subject handles upload for other tables)
            if(!isset($record->$field)) {
                continue;
            }
            //get record field value
            $fieldValue = json_decode($record->$field);
            //skip empty field
            if(!$fieldValue) {
                continue;
            }
            //ERRORE "Cannot use object of type stdClass as array" ALLA RIGA 230
            continue;
            //get posted value
            if($this->action == 'update') {
                $a = $input[$field];
                $fieldObject = json_decode($a);
                //$a = get_object_vars($a);
                //$postedInputs = array_keys($a);
            }
            //loop inputs
            foreach((array) $fieldValue as $hash => $input) {
                //if($this->action == 'delete' || !in_array($hash, $postedInputs)) {
                if($this->action == 'delete' || !isset($fieldObject->$hash)) {
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
     * extracts from a record field, for one upload field, one output paths adding to record property $propertyName
     * @param object $record
     * @param string $field
     * @param array $outputs array of outputs names to be extracted
     * @param string $propertyName name of property with files paths that is added to record
     */
    public function extractFieldOutputs(&$record, $field, $outputs, $propertyName = '_files')
    {
        ;
        if(isset($record->{$field}) && $record->{$field} && $record->{$field} != '[]' && $record->{$field} != '{}') {
            //init files container
            if(!isset($record->$propertyName)) {
                $record->$propertyName = new \stdclass;
            }
            //init fields container
            if(!isset($record->$propertyName->{$field})) {
                $record->$propertyName->{$field} = array();
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
                $record->$propertyName->{$field}[] = $outputsFiles;
            }
        }
    }
    
    /**
     * extracts for a record, for one or more upload fields, one or more outputs paths
     * @param object $record
     * @param array $fieldsOutputs: indexes are fields names, elements are arrays of outputs names
     * @param string $propertyName name of property with files paths that is added to record
     */
    public function extractFieldsOutputs(&$record, $fieldsOutputs, $propertyName = '_files')
    {
        foreach($fieldsOutputs as $field => $outputs) {
            $this->extractFieldOutputs($record, $field, $outputs, $propertyName);
        }
    }
    
    /**
     * extracts for a list of records, for one or more upload fields, one or more outputs paths
     * @param object $record
     * @param array $fieldsOutputs: indexes are fields names, elements are arrays of outputs names
     * @param string $propertyName name of property with files paths that is added to record
     */
    public function extractFieldsOutputsList(&$records, $fieldsOutputs, $propertyName = '_files')
    {
        foreach($records as $record) {
            $this->extractFieldsOutputs($record, $fieldsOutputs, $propertyName);
        }
    }
}