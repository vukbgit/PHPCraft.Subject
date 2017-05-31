<?php
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
        if(isset($this->DBParameters['schema'])) {
            return $this->DBParameters['schema'] . '.';
        }
    }
    
    /**
     * Builds where conditions (using = operator) from an array of values indexed by fields names
     **/
    protected function where($where)
    {
        foreach($where as $field => $value) {
            $this->queryBuilder->where($field, $value);
        }
    }
}