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
        return $this->separatorToCamelCase($name, '_');
    }

    public function realName(string $name): string
    {
        return strtolower($this->camelCaseToSeparator($name, '_'));
    }

    public function realProperty(string $name): string
    {
        return strtolower($this->camelCaseToSeparator($name, '_'));
    }

    public function styledName(string $name): string
    {
        return ucfirst($this->separatorToCamelCase($name, '_'));
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
        return $this->isRemoteIdentifier($name) ? substr($name, 0, -3) : null;
    }

    public function relationProperty(string $field): string|null
    {
        return $this->isRemoteIdentifier($field) ? substr($field, 0, -3) : null;
    }

    public function isRelationProperty(string $name): bool
    {
        return !$this->isRemoteIdentifier($name) && $this->isRemoteIdentifier($name . '_id');
    }
}
