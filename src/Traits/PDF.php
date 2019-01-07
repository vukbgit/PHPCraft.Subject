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
    * PDF adapter instance to generate PDFs from HTML templates
    **/
    protected $pdfFromHtml;

    /**
    * PDF adapter instance to generate PDFs from other PDFs
    **/
    protected $pdfFromPdf;

    /**
     * Inits trait
     **/
    protected function initTraitPDF()
    {
        //default options from HTML
        if($this->pdfFromHtml) {
            $this->pdfFromHtml->setOptions([
                'defaultPaperSize' => 'A4',
                //'defaultPaperOrientation' => 'landscape',
                'defaultFont' => 'Helvetica',
                'tempDir' => sprintf('%s/tmp', $_SERVER['PHP_DOCUMENT_ROOT']),
                'fontDir' => sprintf('%s/tmp', $_SERVER['PHP_DOCUMENT_ROOT']),
                'log_output_file' => sprintf('%s/tmp/log.html', $_SERVER['PHP_DOCUMENT_ROOT'])
            ]);
        }
        //default options from PDF
        if($this->pdfFromPdf) {
            $this->pdfFromPdf->SetFont('Helvetica');
        }
    }

    /************
    * FROM HTML *
    ************/
    /**
     * Injects PDF adapter to generate PDFs from HTML templates
     * NOTE: kept with this name for backward compatibility
     * @param  PHPCraft\PDF\DompdfAdapter $pdfFromHtml
     **/
    public function injectPDF(\PHPCraft\PDF\DompdfAdapter $pdfFromHtml)
    {
        $this->injectPDFFromHtml($pdfFromHtml);
    }

    /**
     * Injects PDF adapter to generate PDFs from HTML templates
     * @param PHPCraft\PDF\DompdfAdapter $pdfFromHtml
     **/
    public function injectPDFFromHtml(\PHPCraft\PDF\DompdfAdapter $pdfFromHtml)
    {
        $this->pdfFromHtml = $pdfFromHtml;
    }

    /**
     * sets PDF options
     * NOTE: mantained for backward compoatibility
     * @param array $pdfOptions for template
     */
    protected function setPDFOptions($pdfOptions = [])
    {
        $this->setFromHtmlOptions($pdfOptions);
    }

    /**
     * sets PDF options
     * @param array $pdfOptions for template
     */
    protected function setFromHtmlOptions($pdfOptions = [])
    {
        $this->pdfFromHtml->setOptions($pdfOptions);
    }

    /**
     * streams PDF from template
     * @param string $pathToTemplate to template
     * @param string $PDFName name of PDF file
     */
    protected function streamPDFFromTemplate($pathToTemplate, $PDFName)
    {
        //render template
        $html = $this->renderTemplate($pathToTemplate);
        //load HTML into pdf
        $this->pdfFromHtml->loadHtml($html);
        //stream
        $this->httpResponse = $this->httpResponse->withHeader('Content-type', 'application/pdf');
        $this->pdfFromHtml->stream($PDFName);
    }

    /***********
    * FROM PDF *
    ***********/

    /**
     * Injects PDF adapter to generate PDFs from other PDFs
     * @param  \FPDI $pdfFromHtml
     **/
    public function injectPDFFromPdf(\setasign\Fpdi\Fpdi $pdfFromPdf)
    {
        $this->pdfFromPdf = $pdfFromPdf;
    }

    /**
     * streams PDF from another PDF
     * @param string $path to PDF to be used as background
     * @param string $orientation: P(ortrait) | L(andscape)
     */
    protected function setBackgroundFromPdf($path, $orientation)
    {
        //add page
        $orientations = ['P' => 'Portrait', 'L' => 'Landscape'];
        $this->pdfFromPdf->AddPage($orientations[$orientation]);
        //set source file
        $this->pdfFromPdf->setSourceFile($path);
        // import page
        $tplIdx = $this->pdfFromPdf->importPage(1);
        // use the imported page, place itand set width
        $this->pdfFromPdf->useTemplate($tplIdx, 0, 0, 297);
    }

    /**
     * Converts an hex color to RGB
     * @param string $hexColor
     */
    protected function convertColorToRGB($color)
    {
        //check if alread an array (supposed to contain RBG values)
        if(is_array($color)) {
            return $color;
        } else {
            $format = '%02x%02x%02x';
            if(strpos($color, '#') === 0) {
                $format = '#' . $format;
            }
            return sscanf($color, $format);
        }
    }

    /**
     * Sets font
     * @param int $size
     * @param string $family
     * @param string $style: mask from B(old) + I(talic) + U(nderline)
     * @param string $color: array of RGB values or hexadecimal value (with optional leading #)
     */
    protected function fromPdfSetFont($size = null, $family = null, $style = null, $color = null)
    {
        //set defaults
        if($size === null) {
            $size = 0;
        }
        if($family === null) {
            $family = '';
        }
        if($style === null) {
            $style = '';
        }
        $this->pdfFromPdf->SetFont($family, $style, $size);
        //color
        if($color !== null) {
            list($r, $g, $b) = $this->convertColorToRGB($color);
            $this->pdfFromPdf->SetTextColor($r, $g, $b);
        }
    }

    /**
     * writes a string
     * @param string $string
     * @param int $x
     * @param int $y
     * @param int $fontSize
     * @param string $fontFamily
     * @param string $fontStyle: mask from B(old) + I(talic) + U(nderline)
     * @param string $fontColor: array of RGB values or hexadecimal value (with optional leading #)
     */
    protected function fromPdfWrite($string, $x = null, $y = null, $fontSize = null, $fontFamily = null, $fontStyle = null, $fontColor = null)
    {
        //position
        if($x !== null) {
            $this->pdfFromPdf->SetX($x);
        }
        if($y !== null) {
            $this->pdfFromPdf->SetY($y, false);
        }
        //font
        if($fontSize !== null || $fontFamily !== null || $fontStyle !== null || $fontColor !== null) {
            $this->fromPdfSetFont($fontSize, $fontFamily, $fontStyle, $fontColor);
        }
        $this->pdfFromPdf->Write(0, $string);
    }

    /**
     * streams PDF from another PDF
     * @param string $PDFName name of PDF file
     */
    protected function streamFromPdf($PDFName)
    {
        $this->pdfFromPdf->Output('D', $PDFName);
    }

    /**
     * returns PDF from another PDF a a string
     * @param string $PDFName name of PDF file
     */
    protected function fromPdfToString()
    {
        return $this->pdfFromPdf->Output('S');
    }
}
