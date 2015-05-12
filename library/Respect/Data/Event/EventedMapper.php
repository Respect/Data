<?php

namespace Respect\Data\Event;

use Respect\Data\AbstractMapper;

class EventedMapper
{

    protected $mapper;

    protected $eventManager;

    public function __construct(AbstractMapper $mapper)
    {
        $this->mapper = $mapper;
        $this->eventManager = new EventManager();
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

    public function on($event, $callback)
    {
        return $this->eventManager->on($event, $callback);
    }

    public function flush()
    {
        $trackedQueue = $this->getTrackedQueue();
        $trackedEntities = $this->getTrackedEntities();

        $this->processFlushQueue($trackedQueue, $trackedEntities, 'pre');
        $flushResult = $this->mapper->flush();
        $this->processFlushQueue($trackedQueue, $trackedEntities, 'post');

        return $flushResult;
    }

    protected function processFlushQueue($queue, $trackedEntities, $eventSuffix)
    {
        $em = $this->eventManager;

        foreach ($queue as $eventType => $objects) {
            foreach ($objects as $entity) {
                $collection = $trackedEntities[$entity];

                $em->dispatch(
                    "{$collection->getName()}:{$eventType}:{$eventSuffix}",
                    array($entity, $collection)
                );
            }
        }
    }

    protected function getTrackedQueue()
    {
        $m = $this->mapper;
        $inserts = $this->getObjectPropertyFromReflection($m, 'new');
        $updates = $this->getObjectPropertyFromReflection($m, 'changed');
        $deletes = $this->getObjectPropertyFromReflection($m, 'removed');

        return array(
            'insert' => $inserts,
            'update' => $updates,
            'delete' => $deletes
        );
    }

    protected function getTrackedEntities()
    {
        return $this->getObjectPropertyFromReflection(
            $this->mapper,
            'tracked'
        );
    }

    private function getObjectPropertyFromReflection(
        $object,
        $property
    ) {
        $ref = new \ReflectionObject($object);
        $refProp = $ref->getProperty($property);
        if ($refProp->isPrivate() || $refProp->isProtected()) {
            $refProp->setAccessible(true);
        }

        return $refProp->getValue($object);
    }
}
