<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use function array_map;
use function explode;
use function implode;
use function preg_match;
use function preg_replace;

/**
 * Default plural table style familiar from frameworks such as Rails, Kohana,
 * Laravel, FuelPHP, etc:
 *
 * authors    posts        categories   posts_categories
 * --------   ---------    ----------   ----------------
 * id         id           id           id
 * name       author_id    name         post_id
 *            title                     category_id
 *
 * Scope/method names stay PHP-conventional singular camelCase (`post`,
 * `postCategory`); only the *table* name pluralizes. That makes `realName` the
 * single point of difference from {@see Standard} — class resolution, foreign
 * keys, and junction names are all singular/snake and inherited unchanged
 * (`composed` is itself expressed through `realName`).
 */
final class Plural extends Standard
{
    public function realName(string $name): string
    {
        $pieces = array_map($this->singularToPlural(...), explode('_', $this->realProperty($name)));

        return implode('_', $pieces);
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
