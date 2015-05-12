<?php

namespace Respect\Data\Event\Interfaces;

interface Listener
{
    
    /**
     * Update listener, can recevei optional args array
     * @param array $args
     * @return void
     */
    function update($args = array());
    
}

