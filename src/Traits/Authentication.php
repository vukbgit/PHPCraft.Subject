<?php
/**
 * authentication trait for a PHPCraft subject
 * traits optional automatic called methods:
 *      setTraitDependencies[trait-name](): calls $this->setTraitDependencies
 *      setTraitInjections[trait-name](): calls $this->setTraitInjections
 *      processRouteTrait[trait-name](): processes route
 *      processConfigurationTrait[trait-name](): processes configuration
 *      initTrait[trait-name](): performs any task needed by trait BEFORE subject action is performed
 * @author vuk <http://vuk.bg.it>
 */
 
namespace PHPCraft\Subject\Traits;
use \Aura\Auth;

trait Authentication{
    
    /**
    * included trait flag 
    **/
    protected $hasAuthentication = true;
    
    /**
    * Auth factory instance
    **/
    protected $authFactory;
    
    /**
     * Sets trait dependencies from other traits
     **/
    protected function setTraitDependenciesAuthentication()
    {
        $this->setTraitDependencies('Authentication', ['Messages', 'Template']);
    }
    
    /**
     * Sets trait needed injections
     **/
    protected function setTraitInjectionsAuthentication()
    {
        $this->setTraitInjections('Authentication', ['authFactory']);
    }
    
    /**
     * Processes configuration
     * @param array $configuration
     **/
    protected function processConfigurationTraitAuthentication(&$configuration)
    {
        //services
        if(!isset($configuration['areas'][AREA]['authentication']['services']) || !$configuration['areas'][AREA]['authentication']['services']) {
            throw new \Exception(sprintf('missing autentication "services" parameter into %s configuration', AREA));
        } else {
            if(!is_array($configuration['areas'][AREA]['authentication']['services'])) {
                $configuration['areas'][AREA]['authentication']['services'] = [$configuration['areas'][AREA]['authentication']['services']];
            }
        }
    }
    
    /**
     * Injects cookies manager instance
     * @param Aura\Auth\AuthFactory $cookies cookies manager instance
     **/
    public function injectAuthFactory(\Aura\Auth\AuthFactory $authFactory)
    {
        $this->authFactory = $authFactory;
    }
    
    /**
     * displays login form
     */
    protected function execLoginForm()
    {
        $this->loadTranslations('form', sprintf('private/global/locales/%s/form.ini', LANGUAGE));
        $this->templateParameters['translations'] = $this->translations;
        $this->renderTemplate();
    }
    
    /**
     * authenticates user looping services defined into configuration[areas][AREA][authentication][services] array and calling authenticate[service-name] method on each service.
     * Authenticate methods must accept as arguments username, password and return returnCode (integer)
     * returnCode can be: 1 (username not found), 2 (wrong password), 3 (login correct but user is disabled), 4 (valid login)
     * messages must be defined into locale as authentication_message_[returnCode]
     */
    protected function execAuthenticate()
    {
        //get input
        $args = array(
            'username' => FILTER_SANITIZE_STRING,
            'password' => FILTER_SANITIZE_STRING
        );
        $input = filter_input_array(INPUT_POST, $args);
        $username = trim($input['username']);
        $password = trim($input['password']);
        $returnCode = 0;
        //loop services
        foreach($this->configuration['areas'][AREA]['authentication']['services'] as $service) {
            try {
                $method = sprintf('authenticate%s', $this->sanitizeAction($service));
                $serviceReturnCode = $this->$method($username, $password, $returnCode);
                //store returnCode if higher than previus one
                if($serviceReturnCode > $returnCode) {
                    $returnCode = $serviceReturnCode;
                }
            } catch(\Error $exception) {
            //no method defined
                throw new \Error(sprintf('no method "%s" defined into class %s for handling authentication service "%s"', $method, $this->buildClassName($this->name), $service));
            }
        }
        if($returnCode !== 4){
            $this->messages->save('cookies','danger',$this->translations[$this->name][sprintf('authentication_message_%d', $returnCode)]);
            $this->httpResponse = $this->httpResponse->withHeader('Location', $this->configuration['basePath'] . $this->configuration['areas'][AREA]['authentication']['loginURL']);
        }else{
            $loginRequestedUrl = $this->cookies->get(sprintf('authenticationRequestedUrl_%s', AREA), $this->configuration['basePath'] . $this->configuration['areas'][AREA]['authentication']['firstPage']);
            $this->cookies->delete(sprintf('authenticationRequestedUrl_%s', AREA));
            $this->httpResponse = $this->httpResponse->withHeader('Location', $loginRequestedUrl);
        }
    }
    
    /**
     * Authenticates user from a htpasswd file
     * @param string $username
     * @param string $password
     * @return int $returnCode: 1 (username not found), 2 (wrong password), 3 (login correct but user is disabled), 4 (valid login)
     */
    protected function authenticateHtpasswd($username, $password)
    {
        $returnCode = 0;
        $path = sprintf('private/%s/configurations/%s/.htpasswd', APPLICATION, AREA);
        $htpasswdAdapter = $this->authFactory->newHtpasswdAdapter($path);
        $loginService = $this->authFactory->newLoginService($htpasswdAdapter);
        $auth = $this->authFactory->newInstance();
        try {
            $loginService->login($auth, array(
                'username' => $username,
                'password' => $password
            ));
            $returnCode = 4;
        } catch(\Aura\Auth\Exception\UsernameNotFound $e) {
            $returnCode = 1;
        } catch(\Aura\Auth\Exception\PasswordIncorrect $e) {
            $returnCode = 2;
        }
        return $returnCode;
    }
    
    /**
     * Execs logout
     */
    protected function execLogout()
    {
        $logoutService = $this->authFactory->newLogoutService();
        $auth = $this->authFactory->newInstance();
        $logoutService->logout($auth);
        $this->httpResponse = $this->httpResponse->withHeader('Location', '/' . $this->configuration['areas'][AREA]['authentication']['loginURL']);
    }
    
    /**
     * checks wether current user is authenticated
     **/
    public function isAuthenticated()
    {
        $auth = $this->authFactory->newInstance();
        $logStatus = $auth->getStatus();
        return $logStatus == 'VALID';
    }
    
    /**
     * gets current user
     **/
    private function getUserData()
    {
        $auth = $this->authFactory->newInstance();
        return null !== $auth->getUserData() ? $auth->getUserData() : false;
    }
    
    /**
     * Sets a property for current user
     **/
    private function setUserData($property, $value)
    {
        $auth = $this->authFactory->newInstance();
        $userData = $auth->getUserData();
        if(!$userData) {
            return;
        }
        $userData[$property] = $value;
        $auth->setUserData($userData);
    }
}