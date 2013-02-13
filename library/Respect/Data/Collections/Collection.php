<?php

namespace Respect\Data\Collections;

use Respect\Data\AbstractMapper;
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
    protected $extras = array();
    
    public function extra($name, $specs)
    {
        $this->extras[$name] = $specs;
        return $this;
    }
    
    public function getExtra($name)
    {
        if ($this->have($name)) {
            return $this->extras[$name];
        }
    }
    
    public function have($name)
    {
        return isset($this->extras[$name]);
    }
 
    public static function using($condition)
    {
        $collection = new self;
        $collection->setCondition($condition);
        return $collection;
    }

    public static function __callStatic($name, $children)
    {
        $collection = new self();
        return $collection->__call($name, $children);
    }

    public function __construct($name=null, $condition = array())
    {
        $this->name = $name;
        $this->condition = $condition;
        $this->last = $this;
    }

    public function __get($name)
    {
        if (isset($this->mapper) && isset($this->mapper->$name)) {
            return $this->stack(clone $this->mapper->$name);
        }
        return $this->stack(new self($name));
    }

    public function __call($name, $children)
    {
        if (!isset($this->name)) {
            $this->name = $name;
            foreach ($children as $child) {
                if ($child instanceof Collection)
                    $this->addChild($child);
                else
                    $this->setCondition($child);
            }
            return $this;
        }
        
        $collection = self::__callStatic($name, $children);

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

        return $this->mapper->persist($object, $this);
    }
    
    public function remove($object)
    {
        if (!$this->mapper)
            throw new \RuntimeException;

        return $this->mapper->remove($object, $this);
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

    public function stack(Collection $collection)
    {
        $this->last->setNext($collection);
        $this->last = $collection;
        return $this;
    }

}
