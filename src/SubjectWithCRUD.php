<?php
/**
 * manages a PHPCraft subject with CRUD functionalities
 * @author vuk <http://vuk.bg.it>
 */

namespace PHPCraft\Subject;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use PHPCraft\Template\RendererInterface;
use PHPCraft\Cookie\CookieInterface;
use PHPCraft\Database\QueryBuilderInterface;
use PHPCraft\Message\Message;
use PHPCraft\Csv\CsvInterface;

abstract class SubjectWithCRUD extends SubjectWithDatabase
{
    use SubjectWithNavigationTrait, SubjectWithGlobalActionsTrait;

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
     * @param Psr\Http\Message\RequestInterface $httpRequest HTTP request handler instance
     * @param Psr\Http\Message\ResponseInterface $httpResponse HTTP response handler instance
     * @param Psr\Http\Message\StreamInterface $httpStream HTTP stream handler instance
     * @param PHPCraft\Template\RendererInterface $templateRenderer template renderer instance
     * @param PHPCraft\Database\QueryBuilderInterface $queryBuilder query builder instance
     * @param PHPCraft\Cookie\CookieInterface $cookie, instance
     * @param PHPCraft\Message\Message $message instance
     * @param PHPCraft\Csv\CsvInterface $csv reader/writer instance
     * @param string $application current PHPCraft application
     * @param string $basePath path from domain root to application root (with trailing and ending slash)
     * @param string $area current PHPCraft area
     * @param string $subject current PHPCraft subject
     * @param string $action current PHPCraft action
     * @param string $language current PHPCraft language code
     * @param string $dbTable database table name
     * @param string $dbView database view name
     * @param string $primaryKey
     * @param array $exportFields view fields to be selected for export
     * @param array $routeParameters informations extracted from current request by route matching pattern
     **/
    public function __construct(
        RequestInterface $httpRequest,
        ResponseInterface $httpResponse,
        StreamInterface $httpStream,
        RendererInterface $templateRenderer,
        CookieInterface $cookie,
        QueryBuilderInterface $queryBuilder,
        Message $message,
        CsvInterface $csv,
        $application,
        $basePath,
        $area,
        $subject,
        $action,
        $language,
        $dbTable = false,
        $dbView = false,
        $primaryKey = false,
        $exportFields = false,
        $routeParameters = array()
    ) {
        parent::__construct($httpRequest, $httpResponse, $httpStream, $templateRenderer, $cookie, $queryBuilder, $application, $basePath, $area, $subject, $action, $language, $routeParameters);
        $this->message = $message;
        $this->message->setCookie($cookie);
        $this->csv = $csv;
        $this->dbTable = $dbTable;
        $this->dbView = $dbView;
        $this->primaryKey = $primaryKey;
        $this->exportFields = $exportFields;
    }
    
    /**
     * Tries to exec current action
     *
     * @throws Exception if there is no method defined to handle action
     **/
    public function execAction()
    {
        $this->templateParameters['primaryKey'] = $this->primaryKey;
        $this->templateParameters['translations'] = $this->translations;
        $this->templateParameters['messages'] = $this->message->get('cookies');
        $this->httpResponse = $this->message->clear('cookies');
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
        //render
        $this->renderTemplate();
    }
    
    /**
    * Adds ordering informations to query based on stored cookies
    */
    protected function setListOrder() {
        $this->templateParameters['orderBy'] = $this->cookie->get('order-by');
        $orderByField = filter_input(INPUT_POST, 'order-by', FILTER_SANITIZE_STRING);
        if(isset($orderByField)){
            $direction = (isset($this->templateParameters['orderBy'][$this->subject][$orderByField]) && $this->templateParameters['orderBy'][$this->subject][$orderByField] == 'ASC') ? 'DESC' : 'ASC';
            $this->cookie->set('order-by['.$this->subject.']['.$orderByField.']', $direction, self::PERMANENT_COOKIES_LIFE);
            $this->templateParameters['orderBy'][$this->subject][$orderByField] = $direction;
        }
        $removeOrderByField = filter_input(INPUT_POST, 'remove-order-by', FILTER_SANITIZE_STRING);
        if(isset($removeOrderByField)){
            $this->cookie->delete('order-by['.$this->subject.']['.$removeOrderByField.']');
            unset($this->templateParameters['orderBy'][$this->subject][$removeOrderByField]);
        }
        if (isset($this->templateParameters['orderBy'][$this->subject])) {
            foreach ($this->templateParameters['orderBy'][$this->subject] as $field => $direction) {
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
        $this->templateParameters['filterBy'] = $this->cookie->get('filter-by');
        if(isset($_POST['filter-by'])){
            $input = filter_input_array(INPUT_POST, $variablesDefinitions);
            foreach ($input as $filterByField => $value) {
                if($value !== '' && $value !== false) {
                    $this->cookie->set('filter-by[' .$this->subject .'][' .$filterByField .']', $value,self::PERMANENT_COOKIES_LIFE);
                    $this->templateParameters['filterBy'][$this->subject][$filterByField] = $value;
                } else {
                    $this->cookie->delete('filter-by[' . $this->subject . '][' . $filterByField .']');
                    unset($this->templateParameters['filterBy'][$this->subject][$filterByField]);
                }
            }
        }
        if(!isset($this->templateParameters['filterBy'][$this->subject])) $this->templateParameters['filterBy'][$this->subject] = array();
    }
    
    /**
     * exports list in a format specified as last route fragment
     */
    protected function execExport()
    {
        $this->{'execExport' . $this->routeParameters['key']}();
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
                $columns[$field] = ucfirst($this->translations[$this->subject][$field]);
            }
        }
        //get records
        $this->queryBuilder->setFetchMode(\PDO::FETCH_ASSOC);
        $records = $this->getList(array_keys($columns));
        //set headers
        $fileName = $fileName ? $fileName : $this->translations[$this->subject]['plural'];
        foreach($this->csv->buildHttpHeaders($fileName) as $header => $value) {
            $this->httpResponse->setHeader($header, $value);
        }
        //build csv
        if($columns) $this->csv->setColumnHeaders(array_values($columns));
        $this->httpResponse->setContent($this->csv->fromObjects('test', $records));
    }
    
    /**
     * Displays insert form
     */
    protected function execInsertForm()
    {
        $this->renderTemplate();
    }
    
    /**
     * Displays update form
     */
    protected function execUpdateForm($updateGlobalAction = array())
    {
        $recordId = isset($this->routeParameters['key']) ? $this->routeParameters['key'] : false;
        $this->templateParameters['subAction'] = 'update';
        $this->queryBuilder->table($this->dbView);
        $this->queryBuilder->where($this->primaryKey,$recordId);
        $this->templateParameters['record'] = $this->queryBuilder->get()[0];
        if($updateGlobalAction) $this->setGlobalAction($updateGlobalAction);
        //render
        $this->renderTemplate();
    }
    
    /**
     * Saves record
     * @param array $arguments fields to be extracted from posted values as required from filter_input_array
     */
    protected function execSave($arguments = array())
    {
        $input = filter_input_array(INPUT_POST, $arguments);
        $this->queryBuilder->table($this->dbTable);
        //subsubject
        if(!$input[$this->primaryKey]) {
            //insert
            try{
                unset($input[$this->primaryKey]);
                $recordId = $this->queryBuilder->insert($input);
                $this->message->save('cookies','success',$this->translations[$this->subject]['insert_success']);
            } catch(\PDOException $exception) {
                $error = $this->queryBuilder->handleQueryException($exception);
                switch($error[0]) {
                    case 'integrity_constraint_violation_duplicate_entry':
                        $message = $this->translations[$this->subject][$error[0].'_'.$error[1]];
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
                $this->message->save('cookies','success',$this->translations[$this->subject]['update_success']);
            } catch(\PDOException $exception) {
                $error = $this->queryBuilder->handleQueryException($exception);
                switch($error[0]) {
                    case 'integrity_constraint_violation_duplicate_entry':
                        $message = $this->translations[$this->subject][$error[0].'_'.$error[1]];
                    break;
                }
                $this->message->save('cookies','danger',$message);
            }
        }
        //redirect to default action
        $this->httpResponse->setHeader('Location', $this->subjectBaseUrl);
    }
    
    /**
     * Displays delete form
     */
    protected function execDeleteForm()
    {
        $recordId = isset($this->routeParameters['key']) ? $this->routeParameters['key'] : false;
        if($recordId) {
            $this->queryBuilder->table($this->dbView);
            $this->queryBuilder->where($this->primaryKey,$recordId);
            $this->templateParameters['record'] = $this->queryBuilder->get()[0];
        }
        //render
        $this->renderTemplate();
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
                $this->message->save('cookies','success',$this->translations[$this->subject]['delete_success']);
            } catch(Exception $e) {
                $error = handleQueryError($e->getCode(),$e->getMessage());
                $message = $translations[CURRENT_SECTION][$error[0].'_'.$error[1]];
                $this->message->save('cookies','danger',$message);
            }
        }
        //redirect to default action
        $this->httpResponse->setHeader('Location', $this->subjectBaseUrl);
    }
}