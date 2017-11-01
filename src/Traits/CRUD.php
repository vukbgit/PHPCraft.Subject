<?php
/**
 * CRUD trait for a PHPCraft subject
 * traits optional automatic called methods:
 *      setTraitDependencies[trait-name](): calls $this->setTraitDependencies
 *      setTraitInjections[trait-name](): calls $this->setTraitInjections
 *      processRouteTrait[trait-name](): processes route
 *      processConfigurationTrait[trait-name](): processes configuration
 *      initTrait[trait-name](): performs any task needed by trait BEFORE subject action is performed
 * @author vuk <http://vuk.bg.it>
 */
 
namespace PHPCraft\Subject\Traits;

trait CRUD{
    
    /**
    * included trait flag 
    **/
    protected $hasCRUD = true;
    
    /**
    * primary key current value(s)
    **/
    protected $primaryKeyValue = [];
    
    /**
     * Sets trait dependencies from other traits
     **/
    public function setTraitDependenciesCRUD()
    {
        $this->setTraitDependencies('CRUD', ['ORM', 'SideMenu', 'Messages']);
    }
    
    /**
     * Processes route
     * @param array $route
     **/
    protected function processRouteTraitCRUD($route)
    {
        
    }
    
    /**
     * Processes configuration
     * @param array $configuration
     **/
    protected function processConfigurationTraitCRUD(&$configuration)
    {
        if(!isset($configuration['subjects'][$this->name]['CRUD'])) {
            throw new \Exception(sprintf('missing CRUD configuration for subject %s', $this->name));
        }
        if(!isset($configuration['subjects'][$this->name]['CRUD']['actions'])) {
            throw new \Exception(sprintf('missing CRUD actions configuration for subject %s', $this->name));
        }
        if(!isset($configuration['subjects'][$this->name]['CRUD']['inputFields'])) {
            throw new \Exception(sprintf('missing CRUD inputFields configuration for subject %s', $this->name));
        }
    }
    
    /**
     * init jobs
     **/
    public function initTraitCRUD()
    {
        //TEMPLATE FUNCTIONS
        //get primary key value(s)
        $this->templateEngine->addFunction('extractPrimaryKeyValue', function ($record, $returnAs) {
            return $this->extractPrimaryKeyValue($record, $returnAs);
        });
        
        //build path to global actions
        if(isset($this->configuration['subjects'][$this->name]['CRUD']['actions']['global'])) {
            foreach($this->configuration['subjects'][$this->name]['CRUD']['actions']['global'] as $action => $properties) {
                $url = isset($properties['url']) ? $properties['url'] : false;
                $this->configuration['subjects'][$this->name]['CRUD']['actions']['global'][$action]['url'] = $this->buildPathToAction($action, $url);
            }
        }
        //check primary key value(s)
        foreach($this->ORMParameters['primaryKey'] as $field) {
            if(isset($this->route['parameters'][$field])) {
                $this->primaryKeyValue[$field] = $this->route['parameters'][$field];
            }
        }
        $this->setTemplateParameter('primaryKeyValue', $this->primaryKeyValue);
    }
    
    /**
     * Builds primary key value for a record
     * @param mixed $record array or object with record data;
     * @param string $returnAs s = string (imploded by | in case of composite key) | a = array
     */
    protected function extractPrimaryKeyValue($record, $returnAs)
    {
        $record = (object) $record;
        $values = [];
        foreach((array) $this->ORMParameters['primaryKey'] as $field) {
            $values[$field] = $record->$field;
        }
        switch($returnAs) {
            case 's':
                return implode('|', $values);
            break;
            case 'a':
                return $values;
            break;
        }   
    }
    
    /**
     * Purges primary key value for a record
     * @param mixed $record array or object with record data
     */
    protected function purgePrimaryKeyValue(&$record)
    {
        foreach((array) $this->ORMParameters['primaryKey'] as $field) {
            if(is_array($record) && array_key_exists($field, $record)) {
                unset($record[$field]);
            } elseif(is_object($record) && isset($record->$field) ) {
                unset($record->$field);
            }
        }
    }
    
    /**
     * Displays list table
     */
    protected function execList()
    {
        //check if parent primary key is into route
        if(!empty($this->ancestors)) {
            end($this->ancestors);
            $parentPrimaryKey = current($this->ancestors);
        } else {
            $parentPrimaryKey = [];
        }
        //get records
        $this->templateParameters['records'] = $this->get($parentPrimaryKey);
        // form translations
        $this->loadTranslations('list', sprintf('private/global/locales/%s/list.ini', LANGUAGE));
        //get table filter
        $this->templateParameters['table_filter'] = [];
        $this->templateParameters['table_filter']['field'] = $this->cookies->get(sprintf('table_filter_%s_field', $this->name));
        $this->templateParameters['table_filter']['input'] = $this->cookies->get(sprintf('table_filter_%s_input', $this->name));
        //render
        $this->renderTemplate();
    }
    
    /**
     * Displays insert form
     */
    protected function execInsertForm()
    {
        // form translations
        $this->loadTranslations('form', sprintf('private/global/locales/%s/form.ini', LANGUAGE));
        // render template
        $this->renderTemplate(sprintf('%s/%s/save-form', AREA, $this->name));
    }
    
    /**
     * Displays update form
     */
    protected function execUpdateForm()
    {
        // form translations
        $this->loadTranslations('form', sprintf('private/global/locales/%s/form.ini', LANGUAGE));
        // get record
        $this->templateParameters['record'] = $this->getByPrimaryKey($this->primaryKeyValue);
        // add global action to be shown into tabs
        $this->configuration['subjects'][$this->name]['CRUD']['actions']['global']['update-form'] = false;
        // render template
        $this->renderTemplate(sprintf('%s/%s/save-form', AREA, $this->name));
    }
    
    /**
     * Displays delete form
     */
    protected function execDeleteForm()
    {
        // get record
        $this->templateParameters['record'] = $this->getByPrimaryKey($this->primaryKeyValue);
        // render template
        $this->renderTemplate();
    }
    
    /**
     * Processes save input before save query, to be overridden by derived class in case of input processing needed
     * @param array $input
     * @return array $input
     */
    protected function processSaveInput($input)
    {
        return $input;
    }
    
    /**
     * Insert record action
     * @param string $redirectAction
     */
    public function execInsert($redirectAction = false)
    {
        // database translations
        $this->loadTranslations('database', sprintf('private/global/locales/%s/database.ini', LANGUAGE));
        //validate and extract input
        $input = $this->processSaveInput(filter_input_array(INPUT_POST, $this->configuration['subjects'][$this->name]['CRUD']['inputFields']));
        if($input) {
            try{
                //ORM update
                $this->purgePrimaryKeyValue($input);
                if($this->insert($input)) {
                    $this->messages->save('cookies','success',sprintf($this->translations[$this->name]['CRUD']['insert-success'], $this->translations[$this->name]['singular']));
                }
            } catch(\PDOException $exception) {
                $this->messages->save('cookies', 'danger', $this->handleError($exception));
                if(!isset($redirectAction)) {
                    $redirectAction = 'insert-form';
                }
            }
        }
        //redirect
        $redirectAction = $redirectAction ? $redirectAction : 'list';
        $this->httpResponse = $this->httpResponse->withHeader('Location', $redirectAction);
    }
    
    /**
     * Update record action
     * @param string $redirectAction
     */
    public function execUpdate($redirectAction = false)
    {
        // database translations
        $this->loadTranslations('database', sprintf('private/global/locales/%s/database.ini', LANGUAGE));
        //validate and extract input
        $input = $this->processSaveInput(filter_input_array(INPUT_POST, $this->configuration['subjects'][$this->name]['CRUD']['inputFields']));
        if($input) {
            //extract primary key value
            $primaryKeyValue = $this->extractPrimaryKeyValue($input, 'a');
            try{
                //ORM insert
                $this->purgePrimaryKeyValue($input);
                if($this->update($primaryKeyValue, $input)) {
                    $this->messages->save('cookies','success',sprintf($this->translations[$this->name]['CRUD']['update-success'], $this->translations[$this->name]['singular']));
                }
            } catch(\PDOException $exception) {
                $this->messages->save('cookies', 'danger', $this->handleError($exception));
                if(!isset($redirectAction)) {
                    $redirectAction = 'update-form/' . $this->extractPrimaryKeyValue($input, 's');
                }
            }
        }
        //redirect
        $redirectAction = $redirectAction ? $redirectAction : 'list';
        $this->httpResponse = $this->httpResponse->withHeader('Location', $redirectAction);
    }
    
    /**
     * Delete record action
     * @param string $redirectAction
     */
    public function execDelete($redirectAction = false)
    {
        // database translations
        $this->loadTranslations('database', sprintf('private/global/locales/%s/database.ini', LANGUAGE));
        //validate and extract input
        $input = $this->processSaveInput(filter_input_array(INPUT_POST, $this->configuration['subjects'][$this->name]['CRUD']['inputFields']));
        if($input) {
            //extract primary key value
            $primaryKeyValue = $this->extractPrimaryKeyValue($input, 'a');
            try{
                //ORM insert
                $this->delete($primaryKeyValue);
                $this->messages->save('cookies','success',sprintf($this->translations[$this->name]['CRUD']['delete-success'], $this->translations[$this->name]['singular']));
            } catch(\PDOException $exception) {
                $this->messages->save('cookies', 'danger', $this->handleError($exception));
            }
        }
        //redirect
        $redirectAction = $redirectAction ? $redirectAction : 'list';
        $this->httpResponse = $this->httpResponse->withHeader('Location', $redirectAction);
    }
    
    /**
     * deletes multiple record
     */
    protected function execDeleteBulk()
    {
        //get values
        $primaryKeyValues = $_POST['primaryKeyValues'];
        // database translations
        $this->loadTranslations('database', sprintf('private/global/locales/%s/database.ini', LANGUAGE));
        $deletedNumber = 0;
        foreach($primaryKeyValues as $recordPrimaryKeyValues) {
            try{
                $this->delete($recordPrimaryKeyValues);
                $deletedNumber++;
            } catch(\PDOException $exception) {
                $this->messages->save('cookies', 'danger', $this->handleError($exception));
            }
        }
        //success message
        if($deletedNumber) {
            $this->messages->save('cookies','success',sprintf($this->translations[$this->name]['CRUD']['delete-bulk-success'], $deletedNumber, $this->translations[$this->name]['plural']));
        }
        //redirection to list action is performed by javascript into jquery post() success function
    }
}