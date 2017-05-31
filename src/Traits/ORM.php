<?php
namespace PHPCraft\Subject\Traits;

trait ORM{
    
    use Database;
    
    /**
    * included trait flag 
    **/
    protected $hasORM = true;
    
    /**
    * specific database object informations: table, view, primaryKey
    **/
    protected $ORMParameters;
    
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
        $this->ORMParameters['primaryKey'] = $primaryKey;
    
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
     **/
    protected function primaryKeyWhere($primaryKeyValue)
    {
        //simple primary key
        if(!is_array($primaryKeyValue)) {
            //check that configured primaryKey is also not an array
            if(is_array($this->ORMParameters['primaryKey'])) {
                throw new \Exception(sprintf('table %s primary key is compound so values must be passed by an array', $this->table()));
            } else {
                $this->queryBuilder->where($this->ORMParameters['primaryKey'], $primaryKeyValue);
            }
        } else {
        //compound primary key
        }
        
        //simple primary key
        if(!is_array($this->ORMParameters['primaryKey'])) {
            //check that value is also not an array
            if(is_array($primaryKeyValue)) {
                throw new \Exception(sprintf('table %s primary key is simple so values must not be passed by an array', $this->table()));
            } else {
                $this->queryBuilder->where($this->ORMParameters['primaryKey'], $primaryKeyValue);
            }
        } else {
        //compound primary key
            if(!is_array($primaryKeyValue)) {
                throw new \Exception(sprintf('table %s primary key is compound so values must be passed by an array', $this->table()));
            } else {
                //loop primary key fields
                foreach($this->ORMParameters['primaryKey'] as $field) {
                    //check field value
                    if(!isset($primaryKeyValue[$field])) {
                        throw new \Exception(sprintf('missing field % value in where clause for table %s compound primary key', $field, $this->table()));
                    } else {
                        $this->queryBuilder->where($field, $primaryKeyValue[$field]);
                    }
                }
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
     * Inserts record
     * @param array $fieldsValues
     * @return mixed $primaryKeyValue id o new record (if query builder insert operation returns it)
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
     * @return mixed $primaryKeyValue 
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