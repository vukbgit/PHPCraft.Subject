<?php
/**
 * ORM trait for a PHPCraft subject
 * traits optional automatic called methods:
 *      setTraitDependencies[trait-name](): calls $this->setTraitDependencies
 *      setTraitInjections[trait-name](): calls $this->setTraitInjections
 *      processRouteTrait[trait-name](): processes route
 *      processConfigurationTrait[trait-name](): processes configuration
 *      initTrait[trait-name](): performs any task needed by trait BEFORE subject action is performed
 * @author vuk <http://vuk.bg.it>
 */
 
namespace PHPCraft\Subject\Traits;

trait ORM{
    
    /**
    * included trait flag 
    **/
    protected $hasORM = true;
    
    /**
    * specific database object informations: table, view, primaryKey
    * primary key is always an array of at least 1 field, even in case of simple primary key definition 
    **/
    protected $ORMParameters;
    
    /**
     * Sets trait dependencies from other traits
     **/
    public function setTraitDependenciesORM()
    {
        $this->setTraitDependencies('ORM', ['Database']);
    }
    
    /**
     * Processes configuration
     * @param array $configuration
     **/
    protected function processConfigurationTraitORM(&$configuration)
    {
        //check parameters
        if(!isset($configuration['subjects'][$this->name]['ORM'])) {
            throw new \Exception(sprintf('missing ORM parameters into %s subject configuration', $this->name));
        } else {
            $parameters = ['table', 'view', 'primaryKey'];
            foreach($parameters as $parameter) {
                if(!isset($configuration['subjects'][$this->name]['ORM'][$parameter]) || !$configuration['subjects'][$this->name]['ORM'][$parameter]) {
                    throw new \Exception(sprintf('missing %s ORM parameters into %s subject configuration', $parameter, $this->name));
                }
            }
            $this->setORMParameters($configuration['subjects'][$this->name]['ORM']['table'], $configuration['subjects'][$this->name]['ORM']['view'], $configuration['subjects'][$this->name]['ORM']['primaryKey']);
        }
    }
    
     /**
     * sets 
     * @param string $table
     * @param string $view
     * @param mixed $primaryKey string | array (in case of compound primary keys)
     **/
    public function setORMParameters($table, $view, $primaryKey)
    {
        $this->ORMParameters['table'] = $table;
        $this->ORMParameters['view'] = $view;
        $this->ORMParameters['primaryKey'] = is_array($primaryKey) ? $primaryKey : [$primaryKey];
    
    }
    
    /**
     * returns table name with eventual schema
     * @return string
     **/
    public function table()
    {
        return $this->schema()
             . $this->ORMParameters['table'];
    }
    
    /**
     * returns view name with eventual schema
     * @return string
     **/
    public function view()
    {
        return $this->schema()
             . $this->ORMParameters['view'];
    }
    
    /**
     * Builds where conditions (using = operator) from an array of values indexed by fields names
     * @param mixed $primaryKey string | array indexed by PK fields in case of compound primary key
     * @throw exception if value(s) do not fit primary key definition
     **/
    protected function primaryKeyWhere($primaryKeyValue)
    {
        //value is not an array
        if(!is_array($primaryKeyValue)) {
            //turn value into array for first primary key field
            $primaryKeyValue = [
                $this->ORMParameters['primaryKey'][0] => $primaryKeyValue
            ];
        }
        //loop primary key fields
        foreach($this->ORMParameters['primaryKey'] as $field) {
            //check value
            if(!isset($primaryKeyValue[$field])) {
                throw new \Exception(sprintf('missing field % value in where clause for table %s compound primary key', $field, $this->table()));
            } else {
                $this->queryBuilder->where($field, $primaryKeyValue[$field]);
            }
        }
    }
    
    /**
     * gets records
     * @param array $where conditions (usiing = operator) in the form field => value
     * @param array $order order in the form field => direction (ASC | DESC)
     * @return array of records
     **/
    public function get($where = array(), $order = array())
    {
        $this->connectToDB();
        $this->queryBuilder->table($this->view());
        //where
        $this->where($where);
        //order
        foreach($order as $field => $direction) {
            $this->queryBuilder->orderBy($field, $direction);
        }
        $records = $this->queryBuilder->get();
        return $records;
    }
    
    /**
     * gets one record, first of recordset
     * @param array $where conditions in the form field => value
     * @param array $order order in the form field => direction (ASC | DESC)
     * @return object record
     **/
    public function getFirst($where = array(), $order = array())
    {
        return current($this->get($where, $order));
    }
    
    /**
     * gets one record by primary key value(s)
     * @param mixed $primaryKeyValue it can be:
     *          a single string value
     *          associative array of values indexed by fields names (compound primary key)
     * @return object record
     **/
    public function getByPrimaryKey($primaryKeyValue)
    {
        $this->connectToDB();
        $this->queryBuilder->table($this->view());
        $this->primaryKeyWhere($primaryKeyValue);
        return current($this->queryBuilder->get());
    }
    
    /**
     * Inserts record
     * @param array $fieldsValues
     * @return mixed $primaryKeyValue id of new record (if query builder insert operation returns it) or false
     */
    public function insert($fieldsValues)
    {
        $this->connectToDB();
        $this->queryBuilder->table($this->table());
        return $this->queryBuilder->insert($fieldsValues);
    }
    
    /**
     * Updates record
     * @param mixed $primaryKeyValue it can be:
     *          a single string value
     *          associative array of values indexed by fields names (compound primary key)
     * @param array $fieldsValues
     * @return mixed $primaryKeyValue or false
     */
    public function update($primaryKeyValue, $fieldsValues)
    {
        $this->connectToDB();
        $this->queryBuilder->table($this->table());
        $this->primaryKeyWhere($primaryKeyValue);
        $this->queryBuilder->update($fieldsValues);
        return $primaryKeyValue;
    }
    
    /**
     * Deletes record
     * @param array $fieldsValues
     */
    public function delete($fieldsValues)
    {
        $this->connectToDB();
        $this->queryBuilder->table($this->table());
        $this->queryBuilder->delete($fieldsValues);
    }
}