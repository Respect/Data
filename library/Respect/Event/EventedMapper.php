<?php

namespace Respect\Event;

use Respect\Data\AbstractMapper;

class EventedMapper
{

    protected $mapper;

    public function __construct(AbstractMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    public function __get($name)
    {
        return $this->mapper->__get($name);
    }

    public function __set($alias, $collection)
    {
        return $this->mapper->__set($alias, $collection);
    }

    public function __call($name, $children)
    {
        return $this->mapper->__call($name, $children);
    }

    public function __isset($alias)
    {
        return $this->mapper->__isset($alias);
    }

    public function flush()
    {
        $this->notifyPreFlush();
        $this->mapper->flush();
        $this->notifyPostFlush();
    }

    public function notifyPreFlush()
    {}

    public function notifyPostFlush()
    {}
}
