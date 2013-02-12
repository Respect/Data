<?php

namespace Respect\Data\Collections;

class Filtered extends Collection
{
    private $filters = array();
    
    public static function by($filter=null, $otherFilter=null, $etc=null)
    {
        $instance = new static();
        $instance->setFilters(func_get_args());
        return $instance;
    }
    
    public function __construct($filter=null, $otherFilter=null, $etc=null)
    {
        $this->filters = func_get_args();
    }
    
    public function getFilters()
    {
        return $this->filters;
    }
    
    public function setFilters(array $filters = array()) 
    {
        $this->filters = $filters;
    }
    
    protected function stack(Collection $collection)
    {
        $this->last = $collection;
        return $this;
    }
}
