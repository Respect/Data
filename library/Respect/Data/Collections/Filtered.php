<?php

namespace Respect\Data\Collections;

class Filtered extends Collection
{   
    protected $filters = array();
   
    public static function by()
    {
        $collection = new static;
        $collection->setFilters(func_get_args());
        return $collection;
    }

    public function setFilters(array $filters)
    {
        return $this->filters = $filters;
    }
    
    public function getFilters()
    {
        return $this->filters;
    }
    
}
