<?php

namespace Respect\Event\Interfaces;

interface EventManager
{
    /**
     * Do the dispatch of a specific event
     * @param string $eventName
     * @param array $args
     *
     * @return void
     */
    public function dispatch($eventName, $args = array());
    
    /**
     * Add action (Listener or callable) for a event
     * @param string $eventName
     * @param \Respect\Event\Interfaces\Listener|callable $action
     */
    public function on($eventName, $action);
    
    /**
     * Remove a Event from the events array
     * @param string $eventName
     */
    public function removeEvent($eventName);
    
    /**
     * Return the events array
     * @return array
     */
    public function getEvents();
    
    /**
     * Search and return event
     * @param string $eventName
     * @return array
     */
    public function getEvent($eventName);
}

