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
     * authenticates user
     */
    protected function execAuthenticate()
    {
        //get input
        $args = array(
            'username' => FILTER_SANITIZE_STRING,
            'password' => FILTER_SANITIZE_STRING
        );
        $input = filter_input_array(INPUT_POST, $args);
        //httpasswd authentication
        $path = sprintf('private/%s/configurations/%s/.htpasswd', APPLICATION, AREA);
        $htpasswdAdapter = $this->authFactory->newHtpasswdAdapter($path);
        $loginService = $this->authFactory->newLoginService($htpasswdAdapter);
        $auth = $this->authFactory->newInstance();
        try {
            $loginService->login($auth, array(
                'username' => $input['username'],
                'password' => $input['password']
            ));
            $error = false;
        } catch(\Aura\Auth\Exception\UsernameNotFound $e) {
            $error = true;
            $message = 'wrong_username';
        } catch(\Aura\Auth\Exception\PasswordIncorrect $e) {
            $error = true;
            $message = 'wrong_password';
        }
        if($error){
            $this->messages->save('cookies','danger',$this->translations[$this->name][$message]);
            $this->httpResponse = $this->httpResponse->withHeader('Location', $this->configuration['basePath'] . $this->configuration['areas'][AREA]['authentication']['loginURL']);
        }else{
            $loginRequestedUrl = $this->cookies->get('authenticationRequestedUrl', $this->configuration['basePath'] . $this->configuration['areas'][AREA]['authentication']['firstPage']);
            $this->cookies->delete('authenticationRequestedUrl');
            $this->httpResponse = $this->httpResponse->withHeader('Location', $loginRequestedUrl);
        }
    }
}