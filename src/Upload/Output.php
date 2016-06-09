<?php

namespace PHPCraft\Subject\Upload;

/**
 * Manages uploads using codeguy/upload  (https://github.com/codeguy/Upload.git)
 *
 * @author vuk <info@vuk.bg.it>
 */
class Output
{
    protected $name;
    protected $destination;
    protected $uploader;
    
    /**
     * constructor
     *
     * @param string $name
     * @param string $destination path to destination directory
     **/
    public function __construct($name, $destination)
    {
        $this->name = $name;
        $this->destination = $destination;
        if(substr($this->destination, -1) != '/') {
            $this->destination .= '/';
        }
    }
    
    /**
     * Gets destination
     **/
    public function getDestination()
    {
        return $this->destination;
    }
}