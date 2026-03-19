<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use function array_pop;
use function explode;
use function implode;
use function strtolower;
use function substr;
use function ucfirst;

final class CakePHP extends Standard
{
    public function realName(string $name): string
    {
        $pieces = explode('_', strtolower($this->camelCaseToSeparator($name, '_')));
        $pieces[] = $this->singularToPlural(array_pop($pieces));

        return implode('_', $pieces);
    }

    public function remoteIdentifier(string $name): string
    {
        return $this->pluralToSingular($name) . '_id';
    }

    public function remoteFromIdentifier(string $name): string|null
    {
        return $this->isRemoteIdentifier($name) ? $this->singularToPlural(substr($name, 0, -3)) : null;
    }

    public function styledName(string $name): string
    {
        $pieces = explode('_', $name);
        $pieces[] = $this->pluralToSingular(array_pop($pieces));

        return ucfirst($this->separatorToCamelCase(implode('_', $pieces), '_'));
    }

    public function composed(string $left, string $right): string
    {
        $pieces = explode('_', $right);
        $pieces[] = $this->singularToPlural(array_pop($pieces));

        return $left . '_' . implode('_', $pieces);
    }
}
