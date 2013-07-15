<?php
/**
 * Created by JetBrains PhpStorm.
 * User: milan
 * Date: 7/4/13
 * Time: 12:59 PM
 * To change this template use File | Settings | File Templates.
 */

abstract class vObject {

    protected $lineHeap;

    protected $valid = true;
    protected $master;

    function __construct(&$master = null){
        if(isset($master)){
            $this->master = &$master;
        }

    }


    function isValid(){
        return isset($this->master) ? $this->master->valid : $this->valid;
    }

    protected function invalidate(){
        if(isset($this->master)){
            $this->master->valid = false;
        } else {
            $this->valid = false;
        }

    }

    function setMaster($master){
        $this->master = $master;
    }

    public function getMaster(){
        return isset($this->master) ? $this->master : $this;
    }

    /**
     * parse a lineHead to component or propertie
     * @return
     */
    //abstract function parse();
}