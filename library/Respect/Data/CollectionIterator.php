<?php

namespace Respect\Data;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class CollectionIterator extends RecursiveArrayIterator
{

    protected $namesCounts = array();

    public static function recursive($target)
    {
        return new RecursiveIteratorIterator(new static($target), 1);
    }

    public function __construct($target=array(), &$namesCounts=array())
    {
        $this->namesCounts = &$namesCounts;
        parent::__construct(is_array($target) ? $target : array($target));
    }

    public function key()
    {
        $name = $this->current()->getName();

        if (isset($this->namesCounts[$name]))
            return $name . ++$this->namesCounts[$name];

        $this->namesCounts[$name] = 1;
        return $name;
    }

    public function hasChildren()
    {
        return $this->current()->hasMore();
    }

    public function getChildren()
    {
        $c = $this->current();
        $pool = array();

        if ($c->hasChildren())
            $pool = $c->getChildren();

        if ($c->hasNext())
            $pool[] = $c->getNext();

        return new static($pool, $this->namesCounts);
    }

}

