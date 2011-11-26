<?php

namespace Respect\Data;

use ArrayAccess;

class Collection implements ArrayAccess
{

    protected $required = true;
    protected $mapper;
    protected $name;
    protected $condition;
    protected $parent;
    protected $next;
    protected $last;
    protected $children = array();

    public static function __callStatic($name, $children)
    {
        $collection = new static($name);

        foreach ($children as $child)
            if ($child instanceof Collection)
                $collection->addChild($child);
            else
                $collection->setCondition($child);
        return $collection;
    }

    public function __construct($name, $condition = array())
    {
        $this->name = $name;
        $this->condition = $condition;
        $this->last = $this;
    }

    public function __get($name)
    {
        return $this->stack(new static($name));
    }

    public function __call($name, $children)
    {
        $collection = static::__callStatic($name, $children);

        return $this->stack($collection);
    }

    public function addChild(Collection $child)
    {
        $clone = clone $child;
        $clone->setRequired(false);
        $clone->setMapper($this->mapper);
        $clone->setParent($this);
        $this->children[] = $clone;
    }
    
    public function persist($object)
    {
        if (!$this->mapper)
            throw new \RuntimeException;
        
        if ($this->next)
            $this->next->persist($object->{"{$this->next->getName()}_id"});
        
        foreach($this->children as $child)
            $child->persist($object->{"{$child->getName()}_id"});
        
        return $this->mapper->persist($object, $this->name);
    }
    
    public function remove($object)
    {
        if (!$this->mapper)
            throw new \RuntimeException;

        return $this->mapper->remove($object, $this->name);
    }

    public function fetch($extra=null)
    {
        if (!$this->mapper)
            throw new \RuntimeException;

        return $this->mapper->fetch($this, $extra);
    }

    public function fetchAll($extra=null)
    {
        if (!$this->mapper)
            throw new \RuntimeException;
        
        return $this->mapper->fetchAll($this, $extra);
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function getCondition()
    {
        return $this->condition;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getNext()
    {
        return $this->next;
    }

    public function getParentName()
    {
        return $this->parent ? $this->parent->getName() : null;
    }

    public function getNextName()
    {
        return $this->next ? $this->next->getName() : null;
    }

    public function hasChildren()
    {
        return!empty($this->children);
    }

    public function hasMore()
    {
        return $this->hasChildren() || $this->hasNext();
    }

    public function hasNext()
    {
        return!is_null($this->next);
    }

    public function isRequired()
    {
        return $this->required;
    }

    public function offsetExists($offset)
    {
        return null;
    }

    public function offsetGet($condition)
    {
        $this->last->condition = $condition;
        return $this;
    }

    public function offsetSet($offset, $value)
    {
        return null;
    }

    public function offsetUnset($offset)
    {
        return null;
    }

    public function setCondition($condition)
    {
        $this->condition = $condition;
    }

    public function setMapper(AbstractMapper $mapper=null)
    {
        foreach ($this->children as $child)
            $child->setMapper($mapper);
        $this->mapper = $mapper;
    }

    public function setParent(Collection $parent)
    {
        $this->parent = $parent;
    }

    public function setNext(Collection $collection)
    {
        $collection->setParent($this);
        $collection->setMapper($this->mapper);
        $this->next = $collection;
    }

    public function setRequired($required)
    {
        $this->required = $required;
    }

    protected function stack(Collection $collection)
    {
        $this->last->setNext($collection);
        $this->last = $collection;
        return $this;
    }

}
