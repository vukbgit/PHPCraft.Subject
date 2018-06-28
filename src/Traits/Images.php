<?php
/**
 * cookies trait for a PHPCraft subject
 * traits optional automatic called methods:
 *      setTraitDependencies[trait-name](): calls $this->setTraitDependencies
 *      setTraitInjections[trait-name](): calls $this->setTraitInjections
 *      processRouteTrait[trait-name](): processes route
 *      processConfigurationTrait[trait-name](): processes configuration
 *      initTrait[trait-name](): performs any task needed by trait BEFORE subject action is performed
 * @author vuk <http://vuk.bg.it>
 */
 
namespace PHPCraft\Subject\Traits;
use PHPCraft\Image\ImageInterface;

trait Images{
    
    /**
    * included trait flag 
    **/
    protected $hasImages = true;
    
    /**
    * Images manager instance
    **/
    protected $images;
    
    /**
     * Sets trait needed injections
     **/
    protected function setTraitInjectionsImages()
    {
        $this->setTraitInjections('Images', ['images']);
    }
    
    /**
     * Injects the image instance
     * @param $images instance of image adapter implementing PHPCraft\Image\ImageInterface
     **/
    public function injectImages(ImageInterface $images)
    {
        $this->images = $images;
    }
    
    /**
     * resizes an image
     * @param string $path
     * @param int $width
     * @param int $height
     */
    protected function resizeImage($path, $width, $height)
    {
        $this->images->open('Gd',$path);
        $this->images->resize($width, $height);
    }
    
    /**
     * Rotates an image
     * @param string $path
     * @param int $angle rotation angle
     */
    protected function rotateImage($path, $angle)
    {
        $this->images->open('Gd',$path);
        $this->images->rotate($angle);
    }
}