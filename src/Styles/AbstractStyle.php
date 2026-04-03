<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function strtoupper;

abstract class AbstractStyle implements Stylable
{
    protected function camelCaseToSeparator(string $name, string $separator = '_'): string
    {
        return (string) preg_replace('/(?<=[a-z])([A-Z])/', $separator . '$1', $name);
    }

    protected function separatorToCamelCase(string $name, string $separator = '_'): string
    {
        $separator = preg_quote($separator, '/');

        return (string) preg_replace_callback(
            '/' . $separator . '([a-zA-Z])/',
            static fn($m) => strtoupper($m[1]),
            $name,
        );
    }
}
