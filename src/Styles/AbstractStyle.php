<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

abstract class AbstractStyle implements Stylable
{
    protected function camelCaseToSeparator(string $name, string $separator = '_'): string
    {
        return preg_replace('/(?<=[a-z])([A-Z])/', $separator.'$1', $name);
    }

    protected function separatorToCamelCase(string $name, string $separator = '_'): string
    {
        $separator = preg_quote($separator, '/');

        return preg_replace_callback(
            "/{$separator}([a-zA-Z])/",
            fn($m) => strtoupper($m[1]),
            $name
        );
    }

    protected function pluralToSingular(string $name): string
    {
        $replacements = [
            '/^(.+)ies$/' => '$1y',
            '/^(.+)s$/' => '$1',
        ];
        foreach ($replacements as $key => $value) {
            if (preg_match($key, $name)) {
                return preg_replace($key, $value, $name);
            }
        }

        return $name;
    }

    protected function singularToPlural(string $name): string
    {
        $replacements = [
            '/^(.+)y$/' => '$1ies',
            '/^(.+)([^s])$/' => '$1$2s',
        ];
        foreach ($replacements as $key => $value) {
            if (preg_match($key, $name)) {
                return preg_replace($key, $value, $name);
            }
        }

        return $name;
    }
}
