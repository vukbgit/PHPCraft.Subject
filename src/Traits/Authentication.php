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
    * Password generator class instance
    **/
    protected $passwordGenerator;
    
    /**
    * Handy flag to remebers if classes uses permissions system
    **/
    protected $usesPermissions = false;
    
    /**
    * User group permissions container, indexed by subject name, each element is an array of granted permissions
    **/
    protected $subjectsPermissions;
    
    /**
    * database drivers password functions
    **/
    protected $dbPasswordFunctions = [
        'pgsql' => "public.CRYPT('%s', %s)"
    ];
    
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
        $injections = ['authFactory'];
        $this->setTraitInjections('Authentication', $injections);
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
        //permissions, must be set and must be an array, it can be empty in case no permission system is needed
        if(!isset($configuration['areas'][AREA]['authentication']['permissions']) || !is_array($configuration['areas'][AREA]['authentication']['permissions'])) {
            throw new \Exception(sprintf('missing autentication "permissions" parameter into %s configuration', AREA));
        } else {
            //permissions are not empty
            if(!empty($configuration['areas'][AREA]['authentication']['permissions'])) {
                //remember that class uses permission system
                $this->usesPermissions = true;
                //roleIdProperty
                if(!isset($configuration['areas'][AREA]['authentication']['roleIdProperty'])) {
                    throw new \Exception(sprintf('missing autentication "roleIdProperty" parameter into %s configuration', AREA));
                }
                //check sub-arrays
                $subArrays = ['subjects', 'roles', 'permissions', 'subject_role_permission'];
                foreach($subArrays as $subArray) {
                    if(!isset($configuration['areas'][AREA]['authentication']['permissions'][$subArray]) || empty($configuration['areas'][AREA]['authentication']['permissions'][$subArray])) {
                        throw new \Exception(sprintf('missing autentication permissions "%s" parameter into %s configuration', $subArray, AREA));
                    }
                }
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
     * Injects password generator
     * @param Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator $passwordGenerator
     **/
    public function injectPasswordGenerator(\Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator $passwordGenerator)
    {
        $this->passwordGenerator = $passwordGenerator;
    }
    
    /**
     * Initialization tasks
     **/
    protected function initTraitAuthentication()
    {
        //flatten role permissions
        if($this->usesPermissions) {
            //get user role(s)
            
        }
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
     * Authenticate methods must accept as arguments auth (authfactory instance), username, password, returnCode (integer, by reference) and return userData (array)
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
        //auth factory instance
        $auth = $this->authFactory->newInstance();
        //loop services
        foreach($this->configuration['areas'][AREA]['authentication']['services'] as $service) {
            try {
                $method = sprintf('authenticate%s', $this->sanitizeAction($service));
                $serviceReturnCode = 0;
                $userData = $this->$method($auth, $username, $password, $serviceReturnCode);
                //store returnCode if higher than previus one
                if($serviceReturnCode > $returnCode) {
                    $returnCode = $serviceReturnCode;
                }
                //valid login
                if($serviceReturnCode === 4) {
                    $auth->setUserData($userData);
                    break;
                }
            } catch(\Error $exception) {
            //no method defined
                throw new \Error(sprintf('no method "%s" defined into class %s for handling authentication service "%s"', $method, $this->buildClassName($this->name), $service));
            }
        }
        //failed login
        if($returnCode !== 4){
            $this->messages->save('cookies','danger',$this->translations[$this->name][sprintf('authentication_message_%d', $returnCode)]);
            $this->httpResponse = $this->httpResponse->withHeader('Location', $this->configuration['basePath'] . $this->configuration['areas'][AREA]['authentication']['loginURL']);
        }else{
        //successful login
            //store permissions
            $this->storeRolesPermissions();
            //redirect
            $loginRequestedUrl = $this->cookies->get(sprintf('authenticationRequestedUrl_%s', AREA), $this->configuration['basePath'] . $this->configuration['areas'][AREA]['authentication']['firstPage']);
            $this->cookies->delete(sprintf('authenticationRequestedUrl_%s', AREA));
            $this->httpResponse = $this->httpResponse->withHeader('Location', $loginRequestedUrl);
        }
    }
    
    /**
     * Authenticates user from a htpasswd file, basic version to be usually overridden
     * @param object $auth
     * @param string $username
     * @param string $password
     * @param int $returnCode: 1 (username not found), 2 (wrong password), 3 (login correct but user is disabled), 4 (valid login)
     * @return int $returnCode: 1 (username not found), 2 (wrong password), 3 (login correct but user is disabled), 4 (valid login)
     */
    protected function authenticateHtpasswd($auth, $username, $password, &$returnCode)
    {
        $returnCode = 0;
        $path = sprintf('private/%s/configurations/%s/.htpasswd', APPLICATION, AREA);
        $htpasswdAdapter = $this->authFactory->newHtpasswdAdapter($path);
        $loginService = $this->authFactory->newLoginService($htpasswdAdapter);
        $userData = false;
        try {
            $loginService->login($auth, array(
                'username' => $username,
                'password' => $password
            ));
            $returnCode = 4;
            $userData = [
                'username' => $username
            ];
        } catch(\Aura\Auth\Exception\UsernameNotFound $e) {
            $returnCode = 1;
        } catch(\Aura\Auth\Exception\PasswordIncorrect $e) {
            $returnCode = 2;
        }
        return $userData;
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
    * USER'S OPERATION
    **/
    
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
    
    /**
    * PERMISSIONS
    **/
    
    /**
     * Gets current user roles
     * @return array of role ids
     **/
    private function getUserRoles()
    {
        $userData = $this->getUserData();
        $userRoles = [];
        if($this->usesPermissions) {
            //get role(s)
            $userRoles = $userData[$this->configuration['areas'][AREA]['authentication']['roleIdProperty']];
            //if only one role, turn to array anyway
            if(!is_array($userRoles)) {
                $userRoles = [$userRoles];
            }
        }
        return $userRoles;
    }
    
    /**
     * Stores current user roles permissions
     **/
    private function storeRolesPermissions()
    {
        $userRoles = $this->getUserRoles();
        //loop permission to extract the ones related to user roles
        $subjects_permissions = [];
        foreach($this->configuration['areas'][AREA]['authentication']['permissions']['subject_role_permission'] as $subject_role_permission) {
            list($subject, $role, $permission) = $subject_role_permission;
            $subject = $this->configuration['areas'][AREA]['authentication']['permissions']['subjects'][$subject];
            //user has role 
            if(in_array($role, $userRoles)) {
                if(!isset($subjects_permissions[$subject])) {
                    $subjects_permissions[$subject] = [];
                }
                $subjects_permissions[$subject][] = $this->configuration['areas'][AREA]['authentication']['permissions']['permissions'][$permission];
            }
        }
        $this->setUserData('subjects_permissions', $subjects_permissions);
    }
    
    /**
     * Gets current user subjects permission
     * @return array of permissions indexed by subjects
     **/
    protected function getUserPermissions()
    {
        $userData = $this->getUserData();
        return $userData['subjects_permissions'];
    }
    
    /**
     * Checks if current user has a certain permission for a subject
     * @param string $subject
     * @param string $permission
     * @return boolean
     **/
    private function hasPermission($subject, $permission)
    {
        $permissions = $this->getUserPermissions();
        return isset($permissions[$subject]) && in_array($permission, $permissions[$subject]);
    }
    
    /**
     * Checks if current user has at least one permission for a subject
     * @param string $subject
     * @return boolean
     **/
    private function hasSubjectPermission($subject)
    {
        $permissions = $this->getUserPermissions();
        return isset($permissions[$subject]) && !empty($permissions[$subject]);
    }
    
    /**
    * DATABASE SERVICE OPERATIONS
    **/
    
    /**
     * Generates password
     * @return string password
     **/
    private function generatePassword() {
        //password settings
        $this->passwordGenerator
            ->setOptionValue(\Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator::OPTION_UPPER_CASE, true)
            ->setOptionValue(\Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator::OPTION_LOWER_CASE, true)
            ->setOptionValue(\Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator::OPTION_NUMBERS, true)
            ->setOptionValue(\Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator::OPTION_SYMBOLS, false)
            ->setLength(8)
            ;
        return $this->passwordGenerator->generatePassword();
    }
    
    /**
     * Generates password for insert SQL query
     * @param string $password
     * @return string sql code for storing password
     **/
    private function buildPasswordSQL($password) {
        $this->connectToDb();
        return $this->queryBuilder->raw(sprintf($this->dbPasswordFunctions[$this->DBParameters['driver']], $password, "GEN_SALT('md5')"));
    }
}