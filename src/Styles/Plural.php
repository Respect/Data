<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use function array_map;
use function explode;
use function implode;
use function preg_match;
use function preg_replace;
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
        return $this->applyFirstMatch($name, [
            '/^(.+)ies$/' => '$1y',
            '/^(.+)s$/' => '$1',
        ]);
    }

    private function singularToPlural(string $name): string
    {
        return $this->applyFirstMatch($name, [
            '/^(.+)y$/' => '$1ies',
            '/^(.+)([^s])$/' => '$1$2s',
        ]);
    }

    /** @param array<string, string> $replacements */
    private function applyFirstMatch(string $name, array $replacements): string
    {
        foreach ($replacements as $pattern => $replacement) {
            if (preg_match($pattern, $name)) {
                return (string) preg_replace($pattern, $replacement, $name);
            }
        }

        return $name;
    }
}
