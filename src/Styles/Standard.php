<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use function strlen;
use function strripos;
use function strtolower;
use function substr;
use function ucfirst;

class Standard extends AbstractStyle
{
    public function styledProperty(string $name): string
    {
        return $name;
    }

    public function realName(string $name): string
    {
        $name = $this->camelCaseToSeparator($name, '_');

        return strtolower($name);
    }

    public function realProperty(string $name): string
    {
        return $name;
    }

    public function styledName(string $name): string
    {
        $name = $this->separatorToCamelCase($name, '_');

        return ucfirst($name);
    }

    public function identifier(string $name): string
    {
        return 'id';
    }

    public function remoteIdentifier(string $name): string
    {
        return $name . '_id';
    }

    public function composed(string $left, string $right): string
    {
        return $left . '_' . $right;
    }

    public function isRemoteIdentifier(string $name): bool
    {
        return strlen($name) - 3 === strripos($name, '_id');
    }

    public function remoteFromIdentifier(string $name): string|null
    {
        if ($this->isRemoteIdentifier($name)) {
            return substr($name, 0, -3);
        }

        return null;
    }
}
