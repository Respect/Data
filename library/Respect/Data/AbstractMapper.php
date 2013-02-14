<?php

namespace Respect\Data;

use Respect\Data\Collections\Collection;
use SplObjectStorage;

abstract class AbstractMapper
{
    protected $style;
    protected $new;
    protected $tracked;
    protected $changed;
    protected $removed;
    protected $collections = array();

    abstract protected function createStatement(Collection $fromCollection, $withExtra=null);
    
    protected function parseHydrated(SplObjectStorage $hydrated)
    {
        $this->tracked->addAll($hydrated);
        $hydrated->rewind();
        return $hydrated->current();
    }
    
    public function getStyle()
    {
        if (null === $this->style) {
            $this->setStyle(new Styles\Standard());
        }
        return $this->style;
    }

    public function setStyle(Styles\Stylable $style)
    {
        $this->style = $style;
        return $this;
    }

    public function __construct()
    {
        $this->tracked  = new SplObjectStorage;
        $this->changed  = new SplObjectStorage;
        $this->removed  = new SplObjectStorage;
        $this->new      = new SplObjectStorage;
    }

    abstract public function flush();
    
    public function reset()
    {
        $this->changed = new SplObjectStorage;
        $this->removed = new SplObjectStorage;
        $this->new = new SplObjectStorage;
    }
    
    public function markTracked($entity, Collection $collection)
    {
        $this->tracked[$entity] = $collection;
        return true;
    }
    
    public function fetch(Collection $fromCollection, $withExtra = null)
    {
        $statement = $this->createStatement($fromCollection, $withExtra);
        $hydrated = $this->fetchHydrated($fromCollection, $statement);
        if (!$hydrated)
            return false;

        return $this->parseHydrated($hydrated);
    }

    public function fetchAll(Collection $fromCollection, $withExtra = null)
    {
        $statement = $this->createStatement($fromCollection, $withExtra);
        $entities = array();

        while ($hydrated = $this->fetchHydrated($fromCollection, $statement))
            $entities[] = $this->parseHydrated($hydrated);

        return $entities;
    }

    public function persist($object, Collection $onCollection)
    {
        $this->changed[$object] = true;

        if ($this->isTracked($object))
            return true;

        $this->new[$object] = true;
        $this->markTracked($object, $onCollection);
        return true;
    }
    
    public function remove($object, Collection $fromCollection)
    {
        $this->changed[$object] = true;
        $this->removed[$object] = true;

        if ($this->isTracked($object))
            return true;

        $this->markTracked($object, $fromCollection);
        return true;
    }
    
    public function isTracked($entity)
    {
        return $this->tracked->contains($entity);
    }

    protected function fetchHydrated(Collection $collection, $statement)
    {
        if (!$collection->hasMore())
            return $this->fetchSingle($collection, $statement);
        else
            return $this->fetchMulti($collection, $statement);
    }

    public function __get($name)
    {
        if (isset($this->collections[$name]))
            return $this->collections[$name];

        $coll = new Collection($name);
        $coll->setMapper($this);

        return $coll;
    }
    
    public function __isset($alias)
    {
        return isset($this->collections[$alias]);
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
        $collection->setMapper($this);
        $this->collections[$alias] = $collection;
    }

}
