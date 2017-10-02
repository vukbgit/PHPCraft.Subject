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

trait PDF{
    
    /**
    * included trait flag 
    **/
    protected $hasPDF = true;
    
    /**
    * PDF adapter instance
    **/
    protected $pdf;
    
    /**
     * Sets trait needed injections
     **/
    protected function setTraitInjectionsPDF()
    {
        $this->setTraitInjections('PDF', ['pdf']);
    }
    
    /**
     * Injects PDF adapter
     * PHPCraft\PDF\DompdfAdapter $pdf
     **/
    public function injectPDF(\PHPCraft\PDF\DompdfAdapter $pdf) {
        $this->pdf = $pdf;
    }
    
    /**
     * Inits trait
     **/
    protected function initTraitPDF()
    {
        //default options
        $this->pdf->setOptions([
            'defaultPaperSize' => 'A4',
            //'defaultPaperOrientation' => 'landscape',
            'defaultFont' => 'Helvetica',
            'tempDir' => sprintf('%s/tmp', $_SERVER['PHP_DOCUMENT_ROOT']),
            'fontDir' => sprintf('%s/tmp', $_SERVER['PHP_DOCUMENT_ROOT']),
            'log_output_file' => sprintf('%s/tmp/log.html', $_SERVER['PHP_DOCUMENT_ROOT'])
        ]);
    }
    
    /**
     * sets PDF options
     * @param array $pdfOptions for template
     */
    public function setPDFOptions($pdfOptions = []) {
        $this->pdf->setOptions($pdfOptions);
    }
    
    /**
     * streams PDF from template
     * @param string $pathToTemplate to template
     * @param string $PDFName name of PDF file
     */
    public function streamPDFFromTemplate($pathToTemplate, $PDFName) {
        //render template
        $html = $this->renderTemplate($pathToTemplate);
        //load HTML into pdf
        $this->pdf->loadHtml($html);
        //stream
        $this->httpResponse = $this->httpResponse->withHeader('Content-type', 'application/pdf');
        $this->pdf->stream($PDFName);
    }
}