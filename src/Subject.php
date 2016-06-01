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
use PHPCraft\Cookie\CookieInterface;

class Subject
{
    protected $httpRequest;
    protected $httpResponse;
    protected $httpStream;
    protected $templateRenderer;
    protected $cookie;
    protected $application;
    protected $area;
    protected $subject;
    protected $action;
    protected $routeParameters;
    protected $templateParameters;
    protected $translations;
    protected $areaAuthentication = false;
    protected $pathToSubject;

    /**
     * Constructor.
     * @param Psr\Http\Message\RequestInterface $httpRequest HTTP request handler instance
     * @param Psr\Http\Message\ResponseInterface $httpResponse HTTP response handler instance
     * @param Psr\Http\Message\StreamInterface $httpStream HTTP stream handler instance
     * @param PHPCraft\Template\RendererInterface $templateRenderer template renderer instance
     * @param PHPCraft\Cookie\CookieInterface $cookie, instance
     * @param string $application current PHPCraft application
     * @param string $area current PHPCraft area
     * @param string $subject current PHPCraft subject
     * @param string $action current PHPCraft action
     * @param string $language current PHPCraft language code
     * @param array $routeParameters informations extracted from current request by route matching pattern
     **/
    public function __construct(
        RequestInterface &$httpRequest,
        ResponseInterface &$httpResponse,
        StreamInterface &$httpStream,
        RendererInterface $templateRenderer,
        CookieInterface $cookie,
        $application,
        $area,
        $subject,
        $action,
        $language,
        $routeParameters = array()
    ) {
        $this->httpRequest =& $httpRequest;
        $this->httpResponse =& $httpResponse;
        $this->httpStream =& $httpStream;
        $this->templateRenderer = $templateRenderer;
        $this->cookie = $cookie;
        $this->application = $application;
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
            'requestedUri' => $httpRequest->getUri(),
            'language' => $this->language
        );
        $this->translations = array();
    }
    
    /**
     * stores the path to current subject
     **/
    public function setPathToSubject($applicationBasePath, $areaBasePath, $subjectBasePath){
        $this->pathToSubject['application'] = $applicationBasePath;
        $this->pathToSubject['area'] = $areaBasePath;
        $this->pathToSubject['subject'] = $subjectBasePath;
        $this->templateParameters['pathToSubject'] = $this->pathToSubject;
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
     * adds an application level translations with the assumption that is sotred into private/application-name/current-language
     * @param string $key key of translations array to store file content into
     * @param string $pathToIniFile file path into private/application-name/curent-language/
     * @throws InvalidArgumentException if file is not found
     **/
    public function addApplicationTranslations($key, $pathToIniFile)
    {
        $path = sprintf('private/%s/locales/%s/%s', $this->application, $this->language, $pathToIniFile);
        $this->addTranslations($key, $path);
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
        $this->cookie->set('backPaths[' . $input['backId'] . '][path]', $input['backPath']);
        $this->cookie->set('backPaths[' . $input['backId'] . '][label]', $input['backLabel']);
    }
    
    /**
     * Gets Stored paths to turn back lately
     **/
    protected function getBackPaths()
    {
        $backPaths = $this->cookie->get('backPaths');
        foreach((array) $backPaths as $backId => $backpath) {
            if($backpath['path'] == $this->httpRequest->getUri()) {
                $this->cookie->delete('backPaths[' . $backId . '][path]');
                $this->cookie->delete('backPaths[' . $backId . '][label]');
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
    
    /**
     * Renders action template
     * @param string $path;
     **/
    protected function renderTemplate($path = false)
    {
        if(!$path) {
            $path = sprintf('%s/%s/%s', $this->area, $this->subject, $this->action);
        }
        $this->templateParameters['translations'] = $this->translations;
        $html = $this->templateRenderer->render($path, $this->templateParameters);
        $this->httpStream->write($html);
    }
}