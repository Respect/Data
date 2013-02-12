<?php

namespace Respect\Data\Collections;

class Filtered extends Collection
{
    public static function __callStatic($name, $children)
    {
        $collection = new self();
        $collection->extra('filters', array());
        return $collection->__call($name, $children);
    }
    
    public static function by($name)
    {
        $collection = new Collection;
        $collection->extra('filters', func_get_args());
        return $collection;
    }   
}
