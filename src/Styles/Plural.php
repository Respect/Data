<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use function array_map;
use function explode;
use function implode;
use function preg_match;
use function preg_replace;
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
        return $this->isRemoteIdentifier($name) ? $this->singularToPlural(substr($name, 0, -3)) : null;
    }

    public function realName(string $name): string
    {
        return implode('_', array_map(
            $this->singularToPlural(...),
            explode('_', strtolower($this->camelCaseToSeparator($name, '_'))),
        ));
    }

    public function styledName(string $name): string
    {
        $pieces = array_map($this->pluralToSingular(...), explode('_', $name));

        return ucfirst($this->separatorToCamelCase(implode('_', $pieces), '_'));
    }

    public function composed(string $left, string $right): string
    {
        return $this->singularToPlural($left) . '_' . $this->singularToPlural($right);
    }

    private function pluralToSingular(string $name): string
    {
        $replacements = [
            '/^(.+)ies$/' => '$1y',
            '/^(.+)s$/' => '$1',
        ];
        foreach ($replacements as $key => $value) {
            if (preg_match($key, $name)) {
                return (string) preg_replace($key, $value, $name);
            }
        }

        return $name;
    }

    private function singularToPlural(string $name): string
    {
        $replacements = [
            '/^(.+)y$/' => '$1ies',
            '/^(.+)([^s])$/' => '$1$2s',
        ];
        foreach ($replacements as $key => $value) {
            if (preg_match($key, $name)) {
                return (string) preg_replace($key, $value, $name);
            }
        }

        return $name;
    }
}
