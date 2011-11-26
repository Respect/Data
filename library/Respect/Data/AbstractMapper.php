<?php

namespace Respect\Data;


abstract class AbstractMapper
{

    protected $collections = array();

    abstract public function persist($object, Collection $onCollection);

    abstract public function remove($object, Collection $fromCollection);

    abstract public function fetch(Collection $fromCollection, $withExtra=null);

    abstract public function fetchAll(Collection $fromCollection, $withExtra=null);

    public function __get($name)
    {
        if (isset($this->collections[$name]))
            return $this->collections[$name];

        $this->collections[$name] = new Collection($name);
        $this->collections[$name]->setMapper($this);

        return $this->collections[$name];
    }

    public function __set($alias, $collection)
    {
        return $this->registerCollection($alias, $collection);
    }

    public function __call($name, $children)
    {
        $collection = Collection::__callstatic($name, $children);
        $collection->setMapper($this);
        return $collection;
    }

    public function registerCollection($alias, Collection $collection)
    {
        $this->collections[$alias] = $collection;
    }

}
