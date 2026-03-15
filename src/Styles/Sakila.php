<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

final class Sakila extends Standard
{
    public function identifier(string $name): string
    {
        return $this->remoteIdentifier($name);
    }
}
