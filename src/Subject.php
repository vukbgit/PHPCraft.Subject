<?php
/**
 * manages a PHPCraft subject
 * @author vuk <http://vuk.bg.it>
 */

namespace PHPCraft\Subject;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Subject
{
    /**
    * subject name
    **/
    protected $subject;
    /**
    * HTTP objects
    **/
    protected $httpRequest;
    protected $httpResponse;
    protected $httpStream;
    /**
    * called route
    **/
    protected $route;
    /**
    * loaded configuration
    **/
    protected $configuration;
    /**
    * loaded translations
    **/
    protected $translations;
    /**
    * Action can be set:
    *   - as route['parameters']['action'] element
    *   - as route['properties']['action'] element
    *   - by calling setAction method
    **/
    protected $action = false;
    
    /**
     * Constructor.
     * @param string $subject
     * @param Psr\Http\Message\RequestInterface $httpRequest HTTP request handler instance
     * @param Psr\Http\Message\ResponseInterface $httpResponse HTTP response handler instance
     * @param Psr\Http\Message\StreamInterface $httpStream HTTP stream handler instance
     * @param array $configuration
     * @param array $route route array with static properties ad URL extracted parameters
     **/
    public function __construct(
        $subject,
        RequestInterface &$httpRequest,
        ResponseInterface &$httpResponse,
        StreamInterface &$httpStream,
        $configuration = array(),
        $route = array()
    ) {
        $this->subject = $subject;
        $this->configuration = $configuration;
        $this->route = $route;
        $this->autoExtractAction();
    }
    
    /**
     * Utilized when reading data from inaccessible properties
     * @param string $propertyName
     * @throws Exception if property is not related to a used trait ('has' prefix) end it's not set
     **/
    public function __get($propertyName)
    {
        //check if property regards a used trait
        if(substr($propertyName, 0, 3) === 'has') {
            return isset($this->$propertyName) && $this->$propertyName === true;
        } else {
            throw new \Exception(sprintf('Undefined property %s', $propertyName));
        }
    }
    
    /**
     * adds a translations ini file content to subject translations
     * @param string $key key of translations array to store file content into
     * @param string $pathToIniFile file path from application root
     * @throws InvalidArgumentException if file is not found
     **/
    public function addTranslations($key, $pathToIniFile)
    {
        $path = $pathToIniFile;
        if(!is_file($path)) {
            throw new \InvalidArgumentException("Path to " . $path . " is not valid");
        } else {
            $this->translations[$key] = parse_ini_file($path,true);
        }
    }
    
    /**
     * adds an application level translations with the assumption that is stored into private/application-name/current-language
     * @param string $key key of translations array to store file content into
     * @param string $pathToIniFile file path inside private/application-name/curent-language/
     * @throws InvalidArgumentException if file is not found
     **/
    public function addApplicationTranslations($key, $pathToIniFile)
    {
        $path = sprintf('private/%s/locales/%s/%s', APPLICATION, LANGUAGE, $pathToIniFile);
        $this->addTranslations($key, $path);
    }
    
    /**
     * sets action
     * @param string $action
     **/
    public function setAction($action){
        $this->action = $action;
    }
    
    /**
     * searches fpor action value into route
     **/
    public function autoExtractAction(){
        if(isset($this->route['parameters']['action'])) {
            $this->action = $this->route['parameters']['action'];
        } else if(isset($this->route['properties']['action'])) {
            $this->action = $this->route['properties']['action'];
        }
    }
    
    /**
     * turns action from slug-like form (with -) to method name (camelcase)
     * @param string $action
     **/
    public function sanitizeAction($action){
        return ucfirst(preg_replace_callback(
            '/[-_](.)/',
            function($matches) {
                return strtoupper($matches[1]);
            },
            $action
        ));
    }
    
    /**
     * tries to exec current action
     * @throws Exception if there is no action or method defined
     **/
    public function execAction()
    {
        //no action defined
        if(!$this->action) {
            throw new \Exception(sprintf('no action defined for subject %s', $this->subject));
        }
        try {
            $this->{'exec'.$this->sanitizeAction($this->action)}();
        } catch(Exception $exception) {
        //no method defined
            throw new Exception(sprintf('no method for handling %s %s %s', AREA, $this->subject, $this->action));
        }
    }
}