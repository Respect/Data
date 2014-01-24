<?php

namespace Respect\Data\Styles;

abstract class AbstractStyle implements Stylable
{

    protected function camelCaseToSeparator($name, $separator = '_')
    {
        return preg_replace('/(?<=[a-z])([A-Z])/', $separator . '$1', $name);
    }

    protected function separatorToCamelCase($name, $separator = '_')
    {
        $separator = preg_quote($separator, '/');
        return preg_replace_callback(
            "/{$separator}([a-zA-Z])/",
            function ($m) { return strtoupper($m[1]); },
            $name
        );
    }

    protected function pluralToSingular($name)
    {
        $replacements = array(
            '/^(.+)ies$/' => '$1y',
            '/^(.+)s$/' => '$1',
        );
        foreach ($replacements as $key => $value)
            if (preg_match($key, $name))
                return preg_replace($key, $value, $name);
    }

    protected function singularToPlural($name)
    {
        $replacements = array(
            '/^(.+)y$/' => '$1ies',
            '/^(.+)([^s])$/' => '$1$2s',
        );
        foreach ($replacements as $key => $value)
            if (preg_match($key, $name))
                return preg_replace($key, $value, $name);
    }


}

