<?php

namespace PHPCraft\Subject\Upload;

/**
 * Manages uploads using codeguy/upload  (https://github.com/codeguy/Upload.git)
 *
 * @author vuk <info@vuk.bg.it>
 */
class UploadFileOutput
{
    protected $destination;
    
    /**
     * constructor
     *
     * @param string $destination path to destination directory
     **/
    public function __construct($destination)
    {
        $this->destination = $destination;
        if(substr($this->destination, -1) != '/') {
            $this->destination .= '/';
        }
    }
}