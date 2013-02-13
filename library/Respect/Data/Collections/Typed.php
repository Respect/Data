<?php

namespace Respect\Data\Collections;

class Typed extends Collection
{
    public static function __callStatic($name, $children)
    {
        $collection = new self();
        $collection->extra('type', '');
        return $collection->__call($name, $children);
    }
    
    public static function by($type)
    {
        $collection = new Collection;
        $collection->extra('type', $type);
        return $collection;
    }   
}
