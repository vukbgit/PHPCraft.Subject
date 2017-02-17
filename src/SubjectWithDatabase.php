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

class SubjectWithDatabase extends Subject
{
    protected $queryBuilder;
    protected $table;
    protected $primaryKey;
    protected $fields;

    /**
     * Constructor.
     * @param Psr\Http\Message\RequestInterface $httpRequest HTTP request handler instance
     * @param Psr\Http\Message\ResponseInterface $httpResponse HTTP response handler instance
     * @param Psr\Http\Message\StreamInterface $httpStream HTTP stream handler instance
     * @param PHPCraft\Template\TemplateInterface $template template renderer instance
     * @param PHPCraft\Cookie\CookieInterface $cookieBuilder, instance
     * @param PHPCraft\Database\QueryBuilderInterface $queryBuilder query builder instance
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
        TemplateInterface $template,
        CookieInterface $cookieBuilder,
        QueryBuilderInterface $queryBuilder,
        $application,
        $area,
        $subject,
        $action,
        $language,
        $routeParameters = array()
    ) {
        parent::__construct($httpRequest, $httpResponse, $httpStream, $template, $cookieBuilder, $application, $area, $subject, $action, $language, $routeParameters);
        $this->queryBuilder = $queryBuilder;
        
    }
    
    /**
     * Connects to database
     *
     * @param string $driver database type
     * @param string $host
     * @param string $database
     * @param string $username
     * @param string $password
     * @param string $charset
     * @param string $collation
     * @param array $options
     **/
    public function connectToDB($driver, $host, $database, $username, $password, $charset = 'utf8', $collation = 'utf8_unicode_ci', $options = array()){
        $this->queryBuilder->connect($driver, $host, $database, $username, $password, $charset, $collation, $options);
    }
    
    /**
     * Set basic query informations
     *
     * @param string $table table/view name
     * @param string $primaryKey
     * @param array $fields
     **/
    public function setQuery($table, $primaryKey, $fields){
        $this->table = $table;
        $this->primaryKey = $primaryKey;
        $this->fields = $fields;
    }
    
    /**
     * Set basic query informations
     *
     * @param string $table table/view name
     * @param string $primaryKey
     * @param array $fields
     **/
    protected function handleError($exception){
        $error = $this->queryBuilder->handleQueryException($exception);
        if($error[0]) {
            $message = $this->translations[$this->subject][$error[0].'_'.$error[1]];
        } else {
            $message = sprintf($this->translations['database']['query_error'],$error[1]);
        }
        $this->message->save('cookies','danger',$message);
    }
}