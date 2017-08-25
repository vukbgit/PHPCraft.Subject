<?php
/**
 * database trait for a PHPCraft subject
 * traits optional automatic called methods:
 *      setTraitDependencies[trait-name](): calls $this->setTraitDependencies
 *      setTraitInjections[trait-name](): calls $this->setTraitInjections
 *      processRouteTrait[trait-name](): processes route
 *      processConfigurationTrait[trait-name](): processes configuration
 *      initTrait[trait-name](): performs any task needed by trait BEFORE subject action is performed
 * @author vuk <http://vuk.bg.it>
 */
 
namespace PHPCraft\Subject\Traits;
use PHPCraft\Database\QueryBuilderInterface;

trait Database{
    
    /**
    * included trait flag 
    **/
    protected $hasDatabase = true;
    
    /**
    * Query builder instance
    **/
    protected $queryBuilder;
    
    /**
    * database parameters
    **/
    protected $DBParameters;
    
    /**
     * Sets trait needed injections
     **/
    protected function setTraitInjectionsDatabase()
    {
        $this->setTraitInjections('Database', ['queryBuilder']);
    }

    /**
     * Processes configuration
     * @param array $configuration
     **/
    protected function processConfigurationTraitDatabase(&$configuration)
    {
        if(!isset($configuration['database'])) {
            throw new \Exception('missing database parameters into configuration');
        } else {
            $this->setDBParameters($configuration['database']['driver'], $configuration['database']['host'], $configuration['database']['username'], $configuration['database']['password'], $configuration['database']['database'], $configuration['database']['schema']);
        }
    }
    
    /**
     * Injects query builder instance
     * @param PHPCraft\Database\QueryBuilderInterface $queryBuilder query builder instance
     **/
    public function injectQueryBuilder(QueryBuilderInterface $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }
    
    /**
     * sets database parameters
     * @param string $driver
     * @param string $host
     * @param string $username
     * @param string $password
     * @param string $database
     * @param string $schema
     * @param string $charset
     * @param string $collation
     * @param array $options
     **/
    protected function setDBParameters($driver, $host, $username, $password, $database, $schema = false, $charset = 'utf8', $collation = 'utf8_unicode_ci', $options = array())
    {
        $this->DBParameters = [
            'driver' => $driver,
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'database' => $database,
            'schema' => $schema,
            'charset' => $charset,
            'collation' => $collation,
            'options' => $options
        ];
    }
    
     /**
     * Connects to database
     **/
    public function connectToDB(){
        //if querybuilder is already connected there is no nedd to connect again
        if($this->queryBuilder->isConnected()) {
            return;
        }
        //check for database parameters
        if(empty($this->DBParameters)) {
            throw new \Exception('missing database parameters');
        }
        $this->queryBuilder->connect(
            $this->DBParameters['driver'],
            $this->DBParameters['host'],
            $this->DBParameters['database'],
            $this->DBParameters['username'],
            $this->DBParameters['password'],
            $this->DBParameters['charset'],
            $this->DBParameters['collation'],
            $this->DBParameters['options']
        );
    }
    
    /**
     * Gets schema
     **/
    public function schema()
    {
        //ORM schema
        if($this->hasORM && $this->ORMParameters['schema']) {
            return $this->ORMParameters['schema'] . '.';
        }
        //global schema
        if(isset($this->DBParameters['schema']) && $this->DBParameters['schema']) {
            return $this->DBParameters['schema'] . '.';
        }
    }
    
    /**
     * Builds where conditions from an array of values indexed by fields names
     * if value is not an array operator is supposed to be =
     * if value is an array first element is used as field value, second one as operator
     **/
    protected function where($where)
    {
        foreach($where as $field => $value) {
            if(!is_array($value)) {
                $this->queryBuilder->where($field, $value);
            } else {
                $this->queryBuilder->where($field, $value[1], $value[0]);
            }
        }
    }
    
    /**
     * Handles a database error
     * @param Exception $exception
     * @retun string $message
     **/
    protected function handleError($exception){
        $error = $this->queryBuilder->handleQueryException($exception);
        if($error[0] && isset($this->translations[$this->name]['query_errors'][$error[0].'_'.$error[1]])) {
            $message = $this->translations[$this->name]['query_errors'][$error[0].'_'.$error[1]];
        } else {
            $message = sprintf($this->translations['database']['query_error'],$error[0], $error[1]);
        }
        return $message;
    }
}