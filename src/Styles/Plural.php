<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use function array_map;
use function explode;
use function implode;
use function strtolower;
use function substr;
use function ucfirst;

/**
 * Default plural table style familiar from frameworks such as Rails, Kohana,
 * Laravel, FuelPHP, etc:
 *
 * authors    posts        categories   posts_categories
 * --------   ---------    ----------   ----------------
 * id         id           id           id
 * name       author_id    name         post_id
 *            title                     category_id
 */
final class Plural extends Standard
{
    public function remoteIdentifier(string $name): string
    {
        return $this->pluralToSingular($name) . '_id';
    }

    public function remoteFromIdentifier(string $name): string|null
    {
        if ($this->isRemoteIdentifier($name)) {
            return $this->singularToPlural(substr($name, 0, -3));
        }

        return null;
    }

    public function realName(string $name): string
    {
        $name    = strtolower($this->camelCaseToSeparator($name, '_'));
        $pieces  = array_map($this->singularToPlural(...), explode('_', $name));

        return implode('_', $pieces);
    }

    public function styledName(string $name): string
    {
        $pieces  = array_map($this->pluralToSingular(...), explode('_', $name));
        $name    = $this->separatorToCamelCase(implode('_', $pieces), '_');

        return ucfirst($name);
    }

    public function composed(string $left, string $right): string
    {
        $left  = $this->singularToPlural($left);
        $right = $this->singularToPlural($right);

        return $left . '_' . $right;
    }
}
