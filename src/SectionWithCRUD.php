<?php
/**
 * manages a PHPCraft section with CRUD functionalities
 * @author vuk <http://vuk.bg.it>
 */

namespace PHPCraft\Section;

use Http\Request;
use Http\Response;
use PHPCraft\Template\RendererInterface;
use PHPCraft\Cookie\CookieBuilderInterface;
use PHPCraft\Database\QueryBuilderInterface;
use PHPCraft\Message\Message;
use PHPCraft\Csv\CsvInterface;

abstract class SectionWithCRUD extends SectionWithDatabase
{
    use TitledSectionTrait, SectionWithNavigationTrait;

    /**
     * life of very persisten cookies in seconds (157680000 = 5 years)
     **/
    const PERMANENT_COOKIES_LIFE = 157680000;
    protected $message;
    protected $csv;
    protected $dbTable;
    protected $dbView;
    protected $primaryKey;
    protected $exportFields;

    /**
     * Constructor.
     * @param Http\Request $request HTTP request handler instance
     * @param Http\Response $response HTTP response handler instance
     * @param PHPCraft\Template\RendererInterface $templateRenderer template renderer instance
     * @param PHPCraft\Database\QueryBuilderInterface $queryBuilder query builder instance
     * @param PHPCraft\Cookie\CookieBuilderInterface $cookieBuilder, instance
     * @param PHPCraft\Message\Message $message instance
     * @param PHPCraft\Csv\CsvInterface $csv reader/writer instance
     * @param string $application current PHPCraft application
     * @param string $basePath path from domain root to application root (with trailing and ending slash)
     * @param string $area current PHPCraft area
     * @param string $section current PHPCraft section
     * @param string $action current PHPCraft action
     * @param string $language current PHPCraft language code
     * @param string $dbTable database table name
     * @param string $dbView database view name
     * @param string $primaryKey
     * @param array $exportFields view fields to be selected for export
     * @param array $routePlaceholders informations extracted from current request by route matching pattern
     **/
    public function __construct(
        Request $request,
        Response $response,
        RendererInterface $templateRenderer,
        CookieBuilderInterface $cookieBuilder,
        QueryBuilderInterface $queryBuilder,
        Message $message,
        CsvInterface $csv,
        $application,
        $basePath,
        $area,
        $section,
        $action,
        $language,
        $dbTable = false,
        $dbView = false,
        $primaryKey = false,
        $exportFields = false,
        $routePlaceholders = array()
    ) {
        parent::__construct($request, $response, $templateRenderer, $cookieBuilder, $queryBuilder, $application, $basePath, $area, $section, $action, $language, $routePlaceholders);
        $this->message = $message;
        $this->message->setCookieBuilder($cookieBuilder);
        $this->csv = $csv;
        $this->dbTable = $dbTable;
        $this->dbView = $dbView;
        $this->primaryKey = $primaryKey;
        $this->exportFields = $exportFields;
    }
    
    /**
     * Sets a section global action
     * @param array $action associative array with keys 'url' (complete url from site root), 'action' and 'label'
     **/
    public function setGlobalAction($action)
    {
        if(!isset($this->templateParameters['sectionGlobalActions'])) $this->templateParameters['sectionGlobalActions'] = array();
        $this->templateParameters['sectionGlobalActions'][] = $action;
    }
    
    /**
     * Sets section global actions
     * @param array associative array with keys 'url' (complete url from site root), 'action' and 'label'
     **/
    public function setGlobalActions($actions)
    {
        if(!isset($this->templateParameters['sectionGlobalActions'])) $this->templateParameters['sectionGlobalActions'] = array();
        foreach($actions as $action){
            $this->setGlobalAction($action);
        }
    }
    
    /**
     * Tries to exec current action
     *
     * @throws Exception if there is no method defined to handle action
     **/
    public function execAction()
    {
        $this->templateParameters['section_title'] = $this->translations[$this->section]['section_title'];
        $this->templateParameters['primaryKey'] = $this->primaryKey;
        $this->templateParameters['translations'] = $this->translations;
        parent::execAction();
    }
    
    /**
     * gets list for the table
     *
     * @param array $fields to be selected
     */
    abstract protected function getList($fields = array());
    
    /**
     * Displays list table
     */
    protected function execList()
    {
        //get records
        $this->templateParameters['records'] = $this->getList();
        //template parameters
        $this->templateParameters['messages'] = $this->message->get('cookies');
        $this->setPageTitle($this->templateParameters['section_title']);
        //render
        $html = $this->templateRenderer->render($this->area . '/' . $this->language . '/' . $this->section . '_' . $this->action, $this->templateParameters);
        $this->response->setContent($html);
    }
    
    /**
    * Adds ordering informations to query based on stored cookies
    */
    protected function setListOrder() {
        $this->templateParameters['orderBy'] = $this->cookieBuilder->get('order-by');
        $orderByField = filter_input(INPUT_POST, 'order-by', FILTER_SANITIZE_STRING);
        if(isset($orderByField)){
            $direction = (isset($this->templateParameters['orderBy'][$this->section][$orderByField]) && $this->templateParameters['orderBy'][$this->section][$orderByField] == 'ASC') ? 'DESC' : 'ASC';
            $this->cookieBuilder->set('order-by['.$this->section.']['.$orderByField.']', $direction, self::PERMANENT_COOKIES_LIFE);
            $this->templateParameters['orderBy'][$this->section][$orderByField] = $direction;
        }
        $removeOrderByField = filter_input(INPUT_POST, 'remove-order-by', FILTER_SANITIZE_STRING);
        if(isset($removeOrderByField)){
            $this->cookieBuilder->delete('order-by['.$this->section.']['.$removeOrderByField.']');
            unset($this->templateParameters['orderBy'][$this->section][$removeOrderByField]);
        }
        if (isset($this->templateParameters['orderBy'][$this->section])) {
            foreach ($this->templateParameters['orderBy'][$this->section] as $field => $direction) {
                $this->queryBuilder->orderBy($field, $direction);
            }
        }
    }
    
    /**
    * Adds ordering informations to query based on stored cookies
    *
    * @param array $variablesDefinitions as required by filter_input_array (http://php.net/manual/en/function.filter-input-array.php)
    * @param Pixie\QueryBuilder\QueryBuilderHandler $query
    */
    protected function setListFilter(array $variablesDefinitions) {
        $this->templateParameters['filterBy'] = $this->cookieBuilder->get('filter-by');
        if(isset($_POST['filter-by'])){
            $input = filter_input_array(INPUT_POST, $variablesDefinitions);
            foreach ($input as $filterByField => $value) {
                if($value !== '' && $value !== false) {
                    $this->cookieBuilder->set('filter-by[' .$this->section .'][' .$filterByField .']', $value,self::PERMANENT_COOKIES_LIFE);
                    $this->templateParameters['filterBy'][$this->section][$filterByField] = $value;
                } else {
                    $this->cookieBuilder->delete('filter-by[' . $this->section . '][' . $filterByField .']');
                    unset($this->templateParameters['filterBy'][$this->section][$filterByField]);
                }
            }
        }
        if(!isset($this->templateParameters['filterBy'][$this->section])) $this->templateParameters['filterBy'][$this->section] = array();
    }
    
    /**
     * exports list in a format specified as last route fragment
     */
    protected function execExport()
    {
        $this->{'execExport' . $this->routePlaceholders['key']}();
    }
    
    /**
     * Exports list in csv format
     *
     * @param array $columns associative array, keys are table/view field names, values are column labels
     * @param string $fileName without .csv extension
     */
    protected function execExportCsv($columns = array(), $fileName = false)
    {
        if(empty($columns)){
            $columns = array();
            foreach($this->exportFields as $field){
                $columns[$field] = ucfirst($this->translations[$this->section][$field]);
            }
        }
        //get records
        $this->queryBuilder->setFetchMode(\PDO::FETCH_ASSOC);
        $records = $this->getList(array_keys($columns));
        //set headers
        $fileName = $fileName ? $fileName : $this->translations[$this->section]['plural'];
        foreach($this->csv->buildHttpHeaders($fileName) as $header => $value) {
            $this->response->setHeader($header, $value);
        }
        //build csv
        if($columns) $this->csv->setColumnHeaders(array_values($columns));
        $this->response->setContent($this->csv->fromObjects('test', $records));
    }
    
    /**
     * Displays save form
     */
    protected function execSaveForm($updateGlobalAction = array())
    {
        $recordId = isset($this->routePlaceholders['key']) ? $this->routePlaceholders['key'] : false;
        if(!$recordId) {
        //insert
            $this->templateParameters['subAction'] = 'insert';
        } else {
        //update
            $this->templateParameters['subAction'] = 'update';
            $this->queryBuilder->table($this->dbView);
            $this->queryBuilder->where($this->primaryKey,$recordId);
            $this->templateParameters['record'] = $this->queryBuilder->get()[0];
            if($updateGlobalAction) $this->setGlobalAction($updateGlobalAction);
        }
        //template parameters
        $this->templateParameters['messages'] = $this->message->get('cookies');
        $this->setPageTitle($this->templateParameters['section_title']);
        //render
        $html = $this->templateRenderer->render($this->area . '/' . $this->language . '/' . $this->section . '_' . $this->action, $this->templateParameters);
        $this->response->setContent($html);
    }
    
    /**
     * Saves record
     * @param array $arguments fields to be extracted from posted values as required from filter_input_array
     */
    protected function execSave($arguments = array())
    {
        $input = filter_input_array(INPUT_POST, $arguments);
        $this->queryBuilder->table($this->dbTable);
        //subsection
        if(!$input[$this->primaryKey]) {
            //insert
            try{
                unset($input[$this->primaryKey]);
                $recordId = $this->queryBuilder->insert($input);
                $this->message->save('cookies','success',$this->translations[$this->section]['insert_success']);
            } catch(\PDOException $exception) {
                $error = $this->queryBuilder->handleQueryException($exception);
                switch($error[0]) {
                    case 'integrity_constraint_violation_duplicate_entry':
                        $message = $this->translations[$this->section][$error[0].'_'.$error[1]];
                    break;
                }
                $this->message->save('cookies','danger',$message);
            }
        } else {
            //update
            try{
                $recordId = $input[$this->primaryKey];
                unset($input[$this->primaryKey]);
                $this->queryBuilder->where($this->primaryKey, $recordId);
                $this->queryBuilder->update($input);
                $this->message->save('cookies','success',$this->translations[$this->section]['update_success']);
            } catch(\PDOException $exception) {
                $error = $this->queryBuilder->handleQueryException($exception);
                switch($error[0]) {
                    case 'integrity_constraint_violation_duplicate_entry':
                        $message = $this->translations[$this->section][$error[0].'_'.$error[1]];
                    break;
                }
                $this->message->save('cookies','danger',$message);
            }
        }
        //redirect to default action
        $this->response->setHeader('Location', $this->sectionBaseUrl);
    }
    
    /**
     * Displays delete form
     */
    protected function execDeleteForm()
    {
        $recordId = isset($this->routePlaceholders['key']) ? $this->routePlaceholders['key'] : false;
        if($recordId) {
            $this->queryBuilder->table($this->dbView);
            $this->queryBuilder->where($this->primaryKey,$recordId);
            $this->templateParameters['record'] = $this->queryBuilder->get()[0];
        }
        //template parameters
        $this->templateParameters['messages'] = $this->message->get('cookies');
        $this->setPageTitle($this->templateParameters['section_title']);
        //render
        $html = $this->templateRenderer->render($this->area . '/' . $this->language . '/' . $this->section . '_' . $this->action, $this->templateParameters);
        $this->response->setContent($html);
    }
    
    /**
     * Deletes record
     */
    protected function execDelete()
    {
        $recordId = filter_input(INPUT_POST, $this->primaryKey, FILTER_VALIDATE_INT);
        if($recordId){
            try{
                $this->queryBuilder->table($this->dbTable);
                //$this->queryBuilder->where($this->primaryKey,$recordId);
                $this->queryBuilder->delete([$this->primaryKey => $recordId]);
                $this->message->save('cookies','success',$this->translations[$this->section]['delete_success']);
            } catch(Exception $e) {
                $error = handleQueryError($e->getCode(),$e->getMessage());
                $message = $translations[CURRENT_SECTION][$error[0].'_'.$error[1]];
                $this->message->save('cookies','danger',$message);
            }
        }
        //redirect to default action
        $this->response->setHeader('Location', $this->sectionBaseUrl);
    }
}