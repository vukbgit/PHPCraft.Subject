<?php
/**
 * manages a PHPCraft subject with CRUD functionalities and a one to many relation with another subject
 * @author vuk <http://vuk.bg.it>
 */

namespace PHPCraft\Subject;

abstract class SubjectWithCRUDChild extends SubjectWithCRUD
{
     protected $parentSubject;
     
    /**
     * Checks parent
     *
     * @throws Exception if parent has not been set
     * @throws Exception if there is no method defined to handle action
     **/
    public function checkParent()
    {
        if(!$this->parentSubject) {
            throw new \Exception('set parent for class ' . get_called_class());
        }
        $this->getParent();
    }
    
    /**
     * sets parent properties
     *
     * @param string $primaryKey
     */
    public function injectParent($parentInstance)
    {
        $this->parentSubject = $parentInstance;
    }
    
    /**
     * gets parent
     */
    private function getParent()
    {
        if(isset($this->routeParameters['parentId'])) {
            $this->templateParameters['parent'] = $this->queryBuilder->table($this->parentSubject->dbView)->where($this->parentSubject->primaryKey, $this->routeParameters['parentId'])->get()[0];
        }
    }
    
    /**
     * gets list for the table
     *
     * @param array $fields to be selected
     */
    public function getList($fields = array())
    {
        $this->queryBuilder->table($this->dbView);
        if(isset($this->templateParameters['parent'])) {
            $this->queryBuilder->where('societa_id', $this->templateParameters['parent']->{$this->parentSubject->primaryKey});
        }
        if($fields) $this->queryBuilder->fields($fields);
        //order
        $this->setListOrder();
        //get
        return $this->queryBuilder->get();
    }
}