<?php

namespace Respect\Data\Collections;

class Mixed extends Collection
{
    public static function __callStatic($name, $children)
    {
        $collection = new self();
        $collection->extra('mixins', array());
        return $collection->__call($name, $children);
    }
    
    public static function with()
    {
        $collection = new Collection;
        $collection->extra('mixins', func_get_args());
        return $collection;
    }
}
