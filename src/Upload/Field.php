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
    protected $uploader;
    protected $outputsFiles;
    protected $inputFile;
    protected $inputHash;
    
    /**
    * array of PHPCraft\Subject\Upload\Output instances
    **/
    protected $outputs;
    
    /**
    * array[
    * type => as defined into Upload adapter
    * options => for the type, if any 
    * message => localized message for rule breaking warning
    **/
    protected $validations;
    
    /**
    * string of html code with placeholders for outputs destination in the form of {{output-name}}
    **/
    protected $previewTemplate;
    
    /**
    * array of html code for each uploadedFile with placeholders replaced by actual outputs files paths
    **/
    protected $previews;
    
    /**
     * constructor
     * @param string $field
     * @param array $validations rules to validate uploaded file against
     * @param string $previewTemplate html template to be shown into preview
     **/
    public function __construct($field, $validations = null, $previewTemplate = null)
    {
        $this->field = $field;
        $this->outputs = array();
        $this->validations = $validations;
        $this->previewTemplate = $previewTemplate;
        $this->outputsFiles = array();
        $this->previews = array();
    }
    
    /**
     * Stores input informations
     **/
    private function setInput()
    {
        $this->inputFile = $_FILES[$this->field]['name'];
        $this->inputHash = hash_file('md5', $_FILES[$this->field]['tmp_name']);
    }
    
    /**
     * Adds an output definition
     * @param string $name
     * @param string $destination path to destination directory
     **/
    public function addOutput($name, $destination)
    {
        $this->outputs[$name] = new Output($name, $destination);
    }
    
    /**
     * Handles an upload
     * @param $uploader adapter following PHPCraft\Upload\UploadInterface
     **/
    public function handleUpload(&$uploader)
    {
        // store uploader
        $this->uploader =& $uploader;
        //store input
        $this->setInput();
        // tell uploader which field is uploaded
        $this->uploader->setField($this->field);
        // loop field outputs
        //$preview = '';
        $i=0;
        foreach($this->outputs as $outputName => $output) {
            // set destination
            $this->uploader->setDestination($output->getDestination());
            // set validation (for first output only)
            if(!$i) {
                // loop validation rules
                foreach($this->validations as $validation) {
                    $options = isset($validation['options']) ? $validation['options'] : null;
                    $message = isset($validation['message']) ? $validation['message'] : null;
                    // set rule
                    $this->uploader->addValidationRule($validation['type'], $options, $message);    
                }
            }
            // upload
            if(!$this->uploader->process()) {
                // failure
                return false;
            } else {
                // success
                $outputPath = $output->getDestination() .  $this->uploader->getUploadedFileInfo()['name'];
                $this->outputsFiles[$outputName] = [
                    'name' => $this->uploader->getUploadedFileInfo()['name'],
                    'path' => $outputPath
                ];
                // inject output path into preview
                //$preview = $this->insertOutputIntoPreview($outputName, $outputPath);
            }
            $i++;
        }
        // store preview
        //$this->previews[] = $preview;
        // close upload
        $this->uploader->close();
        return true;
    }
    
    /**
     * Return input file hash
     * @return array
     **/
    public function getInputHash()
    {
        return $this->inputHash;
    }
    
    /**
     * Return input file name
     * @return array
     **/
    public function getInputFile()
    {
        return $this->inputFile;
    }
    
    /**
     * Return outputs files (generated by upload action) paths and names
     * @return array
     **/
    public function getOutputsFiles()
    {
        return $this->outputsFiles;
    }

    /**
     * insert output path into preview template
     * @param string $outputName
     * @param string $outputPath
     * @return string preview html code with output path inserted (if relative placeholder is contained into preview code)
     **/
    public function insertOutputIntoPreview($outputName, $outputPath)
    {
        return str_replace(
            [
                sprintf('{{%s.path}}', $outputName),
                sprintf('{{%s.name}}', $outputName)
            ],
            [
                $outputPath,
                pathinfo($outputPath,  PATHINFO_BASENAME)
            ],
            $this->previewTemplate
        );
    }
    
     /**
     * Return previewTemplate filled with correct destinations
     * @return array
     **/
    public function getPreviews()
    {
        return $this->previews;
    }
}