<?php
/**
 * manages a PHPCraft subject with CRUD functionalities
 * @author vuk <http://vuk.bg.it>
 */

namespace PHPCraft\Subject;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use PHPCraft\Template\TemplateInterface;
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
    protected $formPostFields;
    protected $exportFields;

    /**
     * Constructor.
     * @param Psr\Http\Message\RequestInterface $httpRequest HTTP request handler instance
     * @param Psr\Http\Message\ResponseInterface $httpResponse HTTP response handler instance
     * @param Psr\Http\Message\StreamInterface $httpStream HTTP stream handler instance
     * @param PHPCraft\Template\TemplateInterface $template template renderer instance
     * @param PHPCraft\Database\QueryBuilderInterface $queryBuilder query builder instance
     * @param PHPCraft\Cookie\CookieInterface $cookie, instance
     * @param PHPCraft\Message\Message $message instance
     * @param PHPCraft\Csv\CsvInterface $csv reader/writer instance
     * @param string $application current PHPCraft application
     * @param string $area current PHPCraft area
     * @param string $subject current PHPCraft subject
     * @param string $action current PHPCraft action
     * @param string $language current PHPCraft language code
     * @param string $dbTable database table name
     * @param string $dbView database view name
     * @param string $primaryKey
     * @param array $postedFieldsDefinition definition of fields to be extracted from POST
     * @param array $exportFields view fields to be selected for export
     * @param array $routeParameters informations extracted from current request by route matching pattern
     **/
    public function __construct(
        RequestInterface &$httpRequest,
        ResponseInterface &$httpResponse,
        StreamInterface &$httpStream,
        TemplateInterface $template,
        CookieInterface $cookie,
        QueryBuilderInterface $queryBuilder,
        Message $message,
        CsvInterface $csv,
        $application,
        $area,
        $subject,
        $action,
        $language,
        $dbTable = false,
        $dbView = false,
        $primaryKey = false,
        $postedFieldsDefinition = false,
        $exportFields = false,
        $routeParameters = array()
    ) {
        parent::__construct($httpRequest, $httpResponse, $httpStream, $template, $cookie, $queryBuilder, $application, $area, $subject, $action, $language, $routeParameters);
        $this->httpRequest = &$httpRequest;
        $this->httpResponse = &$httpResponse;
        $this->httpStream =& $httpStream;
        $this->message = $message;
        $this->message->setCookie($cookie);
        $this->csv = $csv;
        $this->dbTable = $dbTable;
        $this->dbView = $dbView;
        $this->primaryKey = $primaryKey;
        $this->postedFieldsDefinition = $postedFieldsDefinition;
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
        $this->templateParameters['orderBy'] = json_decode($this->cookie->get('order-by'));
        $orderByField = filter_input(INPUT_POST, 'order-by', FILTER_SANITIZE_STRING);
        if(isset($orderByField)){
            if(!isset($this->templateParameters['orderBy'])) $this->templateParameters['orderBy'] = new \stdClass;
            if(!isset($this->templateParameters['orderBy']->{$this->subject})) $this->templateParameters['orderBy']->{$this->subject} = new \stdClass;
            $direction = (isset($this->templateParameters['orderBy']->{$this->subject}->$orderByField) && $this->templateParameters['orderBy']->{$this->subject}->$orderByField == 'ASC') ? 'DESC' : 'ASC';
            $this->templateParameters['orderBy']->{$this->subject}->$orderByField = $direction;
            $this->cookie->set('order-by', json_encode($this->templateParameters['orderBy']), self::PERMANENT_COOKIES_LIFE);
        }
        $removeOrderByField = filter_input(INPUT_POST, 'remove-order-by', FILTER_SANITIZE_STRING);
        if(isset($removeOrderByField)){
            unset($this->templateParameters['orderBy']->{$this->subject}->$removeOrderByField);
            $this->cookie->set('order-by', json_encode($this->templateParameters['orderBy']), self::PERMANENT_COOKIES_LIFE);
        }
        if (isset($this->templateParameters['orderBy']->{$this->subject})) {
            foreach ($this->templateParameters['orderBy']->{$this->subject} as $field => $direction) {
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
        $this->templateParameters['filterBy'] = json_decode($this->cookie->get('filter-by'));
        if(isset($_POST['filter-by'])){
            $input = filter_input_array(INPUT_POST, $variablesDefinitions);
            foreach ($input as $filterByField => $value) {
                if($value !== '' && $value !== false) {
                    //$this->cookie->set('filter-by[' .$this->subject .'][' .$filterByField .']', $value,self::PERMANENT_COOKIES_LIFE);
                    $this->templateParameters['filterBy'][$this->subject][$filterByField] = $value;
                } else {
                    //$this->cookie->delete('filter-by[' . $this->subject . '][' . $filterByField .']');
                    unset($this->templateParameters['filterBy'][$this->subject][$filterByField]);
                }
            }
            $this->cookie->set('filter-by', json_encode($this->templateParameters['filterBy']),self::PERMANENT_COOKIES_LIFE);
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
        // form translations
        $this->addTranslations('form', sprintf('private/global/locales/%s/form.ini', $this->language));
        // render template
        $this->renderTemplate(sprintf('%s/%s/saveForm', $this->area, $this->subject));
    }
    
    /**
     * Displays update form
     */
    protected function execUpdateForm($updateGlobalAction = array())
    {
        //global action
        $this->setGlobalAction(
            [
                'url' => implode('', $this->pathToSubject) . 'updateForm',
                'action' => 'updateForm',
                'label' => $this->translations[$this->area]['operations']['update'] . ' ' . $this->translations[$this->subject]['singular']
            ]
        );
        // form translations
        $this->addTranslations('form', sprintf('private/global/locales/%s/form.ini', $this->language));
        // get record id
        $recordId = isset($this->routeParameters['key']) ? $this->routeParameters['key'] : false;
        // build query
        $this->queryBuilder->table($this->dbView);
        $this->queryBuilder->where($this->primaryKey,$recordId);
        $this->templateParameters['record'] = $this->queryBuilder->get()[0];
        if($updateGlobalAction) $this->setGlobalAction($updateGlobalAction);
        // render template
        $this->renderTemplate(sprintf('%s/%s/saveForm', $this->area, $this->subject));
    }
    
    /**
     * Inserts record
     * @param array $arguments fields to be extracted from posted values as required from filter_input_array
     * @param string $redirectAction
     */
    protected function execInsert($arguments = array(), $redirectAction = null)
    {
        if(empty($arguments) && !empty($this->postedFieldsDefinition)) {
            $arguments = $this->postedFieldsDefinition;
        }
        // database translations
        $this->addTranslations('database', sprintf('private/global/locales/%s/database.ini', $this->language));
        $input = filter_input_array(INPUT_POST, $arguments);
        if($input) {
            $this->queryBuilder->table($this->dbTable);
            try{
                unset($input[$this->primaryKey]);
                $recordId = $this->queryBuilder->insert($input);
                $this->message->save('cookies','success',sprintf($this->translations[$this->subject]['insert_success'], $this->translations[$this->subject]['singular']));
            } catch(\PDOException $exception) {
                $error = $this->queryBuilder->handleQueryException($exception);
                if($error[0]) {
                    $message = $this->translations[$this->subject][$error[0].'_'.$error[1]];
                } else {
                    $message = sprintf($this->translations['database']['query_error'],$error[1]);
                }
                $this->message->save('cookies','danger',$message);
                $redirectAction = 'insert';
            }
        }
        //redirect
        $redirectAction = $redirectAction ? $redirectAction : 'list';
        $this->httpResponse = $this->httpResponse->withHeader('Location', $redirectAction);
    }
    
    /**
     * Inserts record
     * @param array $arguments fields to be extracted from posted values as required from filter_input_array
     * @param string $redirectAction
     */
    protected function execUpdate($arguments = array(), $redirectAction = null)
    {
        if(empty($arguments) && !empty($this->postedFieldsDefinition)) {
            $arguments = $this->postedFieldsDefinition;
        }
        // database translations
        $this->addTranslations('database', sprintf('private/global/locales/%s/database.ini', $this->language));
        $input = filter_input_array(INPUT_POST, $arguments);
        if($input) {
            $this->queryBuilder->table($this->dbTable);
            try{
                $recordId = $input[$this->primaryKey];
                unset($input[$this->primaryKey]);
                $this->queryBuilder->where($this->primaryKey, $recordId);
                $this->queryBuilder->update($input);
                $this->message->save('cookies','success',$this->translations[$this->subject]['update_success']);
            } catch(\PDOException $exception) {
                $error = $this->queryBuilder->handleQueryException($exception);
                if($error[0]) {
                    $message = $this->translations[$this->subject][$error[0].'_'.$error[1]];
                } else {
                    $message = sprintf($this->translations['database']['query_error'],$error[1]);
                }
                $this->message->save('cookies','danger',$message);
                $redirectAction = 'insert';
            }
        }
        //redirect
        $redirectAction = $redirectAction ? $redirectAction : 'list';
        $this->httpResponse = $this->httpResponse->withHeader('Location', $redirectAction);
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
     *
     * @param string $redirectAction
     */
    protected function execDelete($redirectAction = null)
    {
        $recordId = filter_input(INPUT_POST, $this->primaryKey, FILTER_VALIDATE_INT);
        if($recordId){
            try{
                $this->queryBuilder->table($this->dbTable);
                $this->queryBuilder->delete([$this->primaryKey => $recordId]);
                $this->message->save('cookies','success',sprintf($this->translations[$this->subject]['delete_success'], $this->translations[$this->subject]['singular']));
            } catch(Exception $e) {
                $error = handleQueryError($e->getCode(),$e->getMessage());
                $message = $translations[CURRENT_SECTION][$error[0].'_'.$error[1]];
                $this->message->save('cookies','danger',$message);
            }
        }
        //redirect
        $redirectAction = $redirectAction ? $redirectAction : 'list';
        $this->httpResponse = $this->httpResponse->withHeader('Location', $redirectAction);
    }
    
    /**
     * Renders action template
     * @param string $path;
     **/
    protected function renderTemplate($path = false)
    {
        $this->templateParameters['messages'] = $this->message->get('cookies');
        parent::renderTemplate($path);
    }
}