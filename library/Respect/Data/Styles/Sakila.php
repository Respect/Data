<?php

namespace Respect\Data\Styles;

class Sakila extends Standard
{

    public function identifier($name)
    {
        return $this->remoteIdentifier($name);
    }

}

