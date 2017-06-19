<?php
/**
 * manages a PHPCraft subject
 * @author vuk <http://vuk.bg.it>
 */

namespace PHPCraft\Subject;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

abstract class Subject
{
    /**
    * subject name
    **/
    protected $name;
    
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
     * @param string $name of the subject
     * @param object $http objects container
     *          ->request Psr\Http\Message\RequestInterface HTTP request handler instance
     *          ->response Psr\Http\Message\ResponseInterface HTTP response handler instance
     *          ->stream Psr\Http\Message\StreamInterface HTTP stream handler instance
     * @param array $configuration global configuration array, with application, areas and subject(s) elements
     * @param array $route route array with static properties ad URL extracted parameters
     **/
    protected function __construct(
        $name,
        &$http,
        &$configuration = array(),
        $route = array()
    ) {
        $this->name = $name;
        $this->httpRequest =& $http->request;
        $this->httpResponse =& $http->response;
        $this->httpStream =& $http->stream;
        $this->processConfiguration($configuration);
        $this->route = $route;
        $this->autoExtractAction();
    }
    
    public static function factory($subjectName, &$http, &$configuration = array(), $route = array())
    {
        $subjectNameClass = sprintf('%s\%s', APPLICATION_NAMESPACE, str_replace('-', '', ucwords($subjectName, '-')));
        //load subject configuration
        $configuration['subjects'][$subjectName] = require sprintf('private/%s/configurations/%s.php', APPLICATION, $subjectName);
        //instance subject
        return new $subjectNameClass(
            $subjectName,
            $http,
            $configuration,
            $route
        );
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
     * Processes configuration, checks for mandatory parameters, extracts found parameters
     * @param array $configuration
     **/
    protected function processConfiguration($configuration)
    {
        //database
        if($this->hasDatabase) {
            //check parameters
            if(!isset($configuration['database'])) {
                throw new \Exception('missing database parameters into configuration');
            } else {
                $this->setDBParameters($configuration['database']['driver'], $configuration['database']['host'], $configuration['database']['username'], $configuration['database']['password'], $configuration['database']['database'], $configuration['database']['schema']);
            }
        }
        //ORM
        if($this->hasORM) {
            //check parameters
            if(!isset($configuration['subjects'][$this->name]['ORM'])) {
                throw new \Exception(sprintf('missing ORM parameters into %s subject configuration', $this->name));
            } else {
                $parameters = ['table', 'view', 'primaryKey'];
                foreach($parameters as $parameter) {
                    if(!isset($configuration['subjects'][$this->name]['ORM'][$parameter]) || !$configuration['subjects'][$this->name]['ORM'][$parameter]) {
                        throw new \Exception(sprintf('missing %s ORM parameters into %s subject configuration', $parameter, $this->name));
                    }
                }
                $this->setORMParameters($configuration['subjects'][$this->name]['ORM']['table'], $configuration['subjects'][$this->name]['ORM']['view'], $configuration['subjects'][$this->name]['ORM']['primaryKey']);
            }
        }
        //store
        $this->configuration = $configuration;
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
            throw new \InvalidArgumentException(sprintf("Translation file not found into path %s", $path));
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
            throw new \Exception(sprintf('no action defined for subject %s', $this->name));
        }
        try {
            $this->{'exec'.$this->sanitizeAction($this->action)}();
        } catch(Exception $exception) {
        //no method defined
            throw new Exception(sprintf('no method for handling %s %s %s', AREA, $this->name, $this->action));
        }
    }
}