<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

final class CakePHP extends Standard
{
    public function realName(string $name): string
    {
        $name       = $this->camelCaseToSeparator($name, '_');
        $name       = strtolower($name);
        $pieces     = explode('_', $name);
        $pieces[]   = $this->singularToPlural(array_pop($pieces));

        return implode('_', $pieces);
    }

    public function remoteIdentifier(string $name): string
    {
        return $this->pluralToSingular($name).'_id';
    }

    public function remoteFromIdentifier(string $name): ?string
    {
        if ($this->isRemoteIdentifier($name)) {
            return $this->singularToPlural(substr($name, 0, -3));
        }

        return null;
    }

    public function styledName(string $name): string
    {
        $pieces     = explode('_', $name);
        $pieces[]   = $this->pluralToSingular(array_pop($pieces));
        $name       = $this->separatorToCamelCase(implode('_', $pieces), '_');

        return ucfirst($name);
    }

    public function composed(string $left, string $right): string
    {
        $pieces     = explode('_', $right);
        $pieces[]   = $this->singularToPlural(array_pop($pieces));
        $right      = implode('_', $pieces);

        return "{$left}_{$right}";
    }
}
