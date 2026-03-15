<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

class Sakila extends Standard
{
    public function identifier($name)
    {
        return $this->remoteIdentifier($name);
    }
}
