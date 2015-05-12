<?php

namespace Respect\Event\Data;

use Respect\Data\Event\Interfaces\EventManager as EventManagerInterface;
use Respect\Data\Event\Interfaces\Listener;

class EventManager implements EventManagerInterface
{
    /*
     * This is the events array
     * @var array
     * @example
     * array(
     *     "EventName1" => array(
     *         0 => callable,
     *         1 => concreteListener
     *         ...
     *     )
     *     ...
     * )
     */
    private $events;

    /**
     * Construct
     */
    public function __construct()
    {
        $this->events = array();
    }

    /**
     * Add a callable or listener to a new or existent event
     * @param string $eventName The event to attach the listener
     * @param \Respect\Event\Interfaces\Listener|callable $action The event listener callback
     * @return void
     */
    public function on($eventName, $action)
    {
        switch (true) {
        case ( ($action instanceof \callable) || ($action instanceof Listener) ):

            // The block below you can use for control or remove it
            $events = $this->getEvents();
            if (array_key_exists("attach", $events)) {
                $this->dispatch("attach", array($eventName, $action));
            }

            $this->events[$eventName][] = $action;
            break;
        default:
            $message = 'Invalid type of action provided for event manager';
            throw new \InvalidArgumentException($message, 400);
        }
    }

    /**
     * Dispatch event
     *
     * If your event have more than one action, unless all the actions doesn't have
     * any args, the args array need to be a array of arrays, each index of the
     * args array needs to correspond to the index of the actions in the event
     * array to work properly.
     *
     * @param string $eventName The event to be dispatched
     * @param array $args Optional args for event actions
     *
     * @return void
     */
    public function dispatch($eventName, $args = array())
    {
        $events = $this->getEvents();
        if (array_key_exists($eventName, $events)) {
            $argsIndex = 0;
            foreach ($events[$eventName] as $action) {
                switch (true) {
                case $action instanceof callable:
                    if ( (count($events[$eventName]) > 1) && !empty($args) ) {
                        call_user_func_array($action, $args[$argsIndex]);
                    } else {
                        call_user_func_array($action, $args);
                    }
                    break;
                case $action instanceof Listener:
                    if ( (count($events[$eventName]) > 1) && !empty($args) ) {
                        $action->update($args[$argsIndex]);
                    } else {
                        $action->update($args);
                    }
                    break;
                default:
                    $message = 'Invalid type of action provided on event manager';
                    throw new \InvalidArgumentException($message, 400); 
                }
                $argsIndex++;
            }
        }

    }

    /**
     * Return events array
     * @return array
     */
    public function getEvents()
    {
        return $this->events;
    }

    /**
     * Search and return event from the events array
     * @param string $eventName the event name
     * @return array
     */
    public function getEvent($eventName)
    {
        $events = $this->getEvents();
        if (array_key_exists($eventName, $events)) {
            return $events[$eventName];
        }
        return null;
    }

    /**
     * Remove event from events array
     * @param string $eventName The event to be removed
     * @return void
     */
    public function removeEvent($eventName)
    {
        $events = $this->getEvents();
        if (array_key_exists($eventName, $events)) {
            $this->events = array_splice($events, $eventName, 1);
        }
    }

}

