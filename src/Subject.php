<?php
/**
 * manages a PHPCraft subject
 * @author vuk <http://vuk.bg.it>
 */

namespace PHPCraft\Subject;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use PHPCraft\Template\RendererInterface;
use PHPCraft\Cookie\CookieBuilderInterface;

class Subject
{
    protected $httpRequest;
    protected $httpResponse;
    protected $httpStream;
    protected $templateRenderer;
    protected $cookieBuilder;
    protected $application;
    protected $basePath;
    protected $area;
    protected $subject;
    protected $action;
    protected $routeParameters;
    protected $templateParameters;
    protected $translations;
    protected $areaAuthentication = false;

    /**
     * Constructor.
     * @param Psr\Http\Message\RequestInterface $httpRequest HTTP request handler instance
     * @param Psr\Http\Message\ResponseInterface $httpResponse HTTP response handler instance
     * @param Psr\Http\Message\StreamInterface $httpStream HTTP stream handler instance
     * @param PHPCraft\Template\RendererInterface $templateRenderer template renderer instance
     * @param PHPCraft\Cookie\CookieBuilderInterface $cookieBuilder, instance
     * @param string $application current PHPCraft application
     * @param string $basePath path from domain root to application root (with trailing and ending slash)
     * @param string $area current PHPCraft area
     * @param string $subject current PHPCraft subject
     * @param string $action current PHPCraft action
     * @param string $language current PHPCraft language code
     * @param array $routeParameters informations extracted from current request by route matching pattern
     **/
    public function __construct(
        RequestInterface $httpRequest,
        ResponseInterface $httpResponse,
        StreamInterface $httpStream,
        RendererInterface $templateRenderer,
        CookieBuilderInterface $cookieBuilder,
        $application,
        $basePath,
        $area,
        $subject,
        $action,
        $language,
        $routeParameters = array()
    ) {
        $this->httpRequest = $httpRequest;
        $this->httpResponse = $httpResponse;
        $this->httpStream = $httpStream;
        $this->templateRenderer = $templateRenderer;
        $this->cookieBuilder = $cookieBuilder;
        $this->application = $application;
        $this->basePath = $basePath;
        $this->area = $area;
        $this->subject = $subject;
        $this->action = $action;
        $this->language = $language;
        $this->routeParameters = $routeParameters;
        $this->templateParameters = array(
            'application' => $this->application,
            'area' => $this->area,
            'subject' => $this->subject,
            'action' => $this->action,
            'basePath' => $basePath,
            'requestedUri' => $httpRequest->getUri(),
            'language' => $this->language
        );
        $this->translations = array();
        $this->getPathToSubject();
    }
    
    /**
     * stores the path to current subject
     **/
    public function getPathToSubject(){
        $uriFragments = explode('/',$this->httpRequest->getUri());
        $pathToSubject = [];
        foreach((array) $uriFragments as $fragment) {
            if($fragment == $this->subject) {
                break;
            }
            $pathToSubject[] = $fragment;
        }
        $this->templateParameters['pathToSubject'] = implode('/',$pathToSubject);
    }
    
    /**
     * adds a translations ini file content to subject translations
     * @param string $key key of file content into translations array
     * @param string $pathToIniFile file path into private/local/locale/
     * @param string $folder subfolder of private to look translations into
     * @throws InvalidArgumentException if file is not found
     **/
    public function addTranslations($key, $pathToIniFile, $folder = false)
    {
        if(!$folder) $folder = $this->application;
        $path = PATH_TO_ROOT . 'private/' . $folder . '/locale/' . $pathToIniFile;
        if(!is_file($path)) {
            throw new \InvalidArgumentException("Path to " . $path . " is not valid");
        } else {
            $this->translations[$key] = parse_ini_file($path,true);
        }
    }
    
    /**
     * tries to exec current action
     * @throws Exception if there is no method defined to handle action
     **/
    public function execAction()
    {
        try {
            $this->templateParameters['area'] = $this->area;
            $this->templateParameters['areaAuthentication'] = $this->areaAuthentication;
            $this->getBackPaths();
            $this->{'exec'.ucfirst($this->action)}();
        } catch(Exception $exception) {
            throw new Exception(sprintf('no method for handling %s %s %s', $this->area, $this->subject, $this->action));
        }
    }
    
    /**
     * Stores a path to turn back lately
     **/
    public function execBackPath()
    {
        $arguments = array(
            'backId' => FILTER_SANITIZE_STRING,
            'backPath' =>  array(
                        'filter' => FILTER_SANITIZE_URL,
                        'flags' => FILTER_FLAG_PATH_REQUIRED
                    ),
            'backLabel' => FILTER_SANITIZE_STRING,
        );
        $input = filter_input_array(INPUT_POST, $arguments);
        $this->cookieBuilder->set('backPaths[' . $input['backId'] . '][path]', $input['backPath']);
        $this->cookieBuilder->set('backPaths[' . $input['backId'] . '][label]', $input['backLabel']);
    }
    
    /**
     * Gets Stored paths to turn back lately
     **/
    protected function getBackPaths()
    {
        $backPaths = $this->cookieBuilder->get('backPaths');
        foreach((array) $backPaths as $backId => $backpath) {
            if($backpath['path'] == $this->httpRequest->getUri()) {
                $this->cookieBuilder->delete('backPaths[' . $backId . '][path]');
                $this->cookieBuilder->delete('backPaths[' . $backId . '][label]');
                unset($backPaths[$backId]);
            }
        }
        $this->templateParameters['backPaths'] = $backPaths;
    }
    
    /**
     * Sets user authentication for current area
     * @param boolean $authenticated;
     **/
    public function setAreaAuthentication($authenticated)
    {
        $this->areaAuthentication = $authenticated;
    }
    
    /**
     * Sets a template parameter
     * @param string $key;
     * @param mixed $value;
     **/
    public function setTemplateParameter($key, $value)
    {
        $this->templateParameters[$key] = $value;
    }
}