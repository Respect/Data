<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use function strlen;
use function strripos;
use function substr;

final class NorthWind extends Standard
{
    public function realName(string $name): string
    {
        return $name;
    }

    public function styledName(string $name): string
    {
        return $name;
    }

    public function styledProperty(string $name): string
    {
        return $name;
    }

    public function realProperty(string $name): string
    {
        return $name;
    }

    public function composed(string $left, string $right): string
    {
        return $this->pluralToSingular($left) . $right;
    }

    public function identifier(string $name): string
    {
        return $this->pluralToSingular($name) . 'ID';
    }

    public function remoteIdentifier(string $name): string
    {
        return $this->pluralToSingular($name) . 'ID';
    }

    public function isRemoteIdentifier(string $name): bool
    {
        return strlen($name) - 2 === strripos($name, 'ID');
    }

    public function remoteFromIdentifier(string $name): string|null
    {
        return $this->isRemoteIdentifier($name) ? $this->singularToPlural(substr($name, 0, -2)) : null;
    }

    public function relationProperty(string $field): string|null
    {
        return $this->isRemoteIdentifier($field) ? substr($field, 0, -2) : null;
    }

    public function isRelationProperty(string $name): bool
    {
        return !$this->isRemoteIdentifier($name) && $this->isRemoteIdentifier($name . 'ID');
    }
}
