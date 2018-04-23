<?php
/**
 * template trait for a PHPCraft subject
 * traits optional automatic called methods:
 *      setTraitDependencies[trait-name](): calls $this->setTraitDependencies
 *      setTraitInjections[trait-name](): calls $this->setTraitInjections
 *      processRouteTrait[trait-name](): processes route
 *      processConfigurationTrait[trait-name](): processes configuration
 *      initTrait[trait-name](): performs any task needed by trait BEFORE subject action is performed
 * @author vuk <http://vuk.bg.it>
 */
 
namespace PHPCraft\Subject\Traits;

use PHPCraft\PDF\TemplateInterface;

trait Csv{
    
    /**
    * included trait flag 
    **/
    protected $hasCsv = true;
    
    /**
    * Csv adapter instance
    **/
    protected $csv;
    
    /**
     * Sets trait needed injections
     **/
    protected function setTraitInjectionsCsv()
    {
        $this->setTraitInjections('Csv', ['csv']);
    }
    
    /**
     * Injects Csv adapter
     * @param PHPCraft\Csv\CsvInterface $csv
     **/
    public function injectCsv(\PHPCraft\Csv\CsvInterface $csv) {
        $this->csv = $csv;
    }
    
    /**
     * Outputs a csv file for download
     * @param string $fileName
     * @param string $content: csv text content
     **/
    protected function outputCsv($fileName, $content) {
        //headers
        foreach($this->csv->buildHttpHeaders($fileName) as $header => $value) {
            $this->httpResponse = $this->httpResponse->withHeader($header, $value);
        }
        //content
        $this->httpStream->write($content);
    }
    
    /**
     * Outputs a csv file for download from a recordset
     * @param string $fileName
     * @param string $content: csv text content
     **/
    protected function outputCsvFromRecords($fileName, $records) {
        //content
        $content = $this->csv->fromObjects($fileName, $records);
        $this->outputCsv($fileName, $content);
    }
}