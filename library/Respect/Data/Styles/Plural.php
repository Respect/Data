<?php

namespace Respect\Data\Styles;

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
 */
class Plural extends Standard
{
    public function remoteIdentifier($name)
    {
        return $this->pluralToSingular($name) . '_id';
    }

    public function remoteFromIdentifier($name)
    {
        if ($this->isRemoteIdentifier($name)) {
            return $this->singularToPlural(substr($name, 0, -3));
        }
    }

    public function realName($name)
    {
        $name    = strtolower($this->camelCaseToSeparator($name, '_'));
        $pieces  = array_map(array($this, 'singularToPlural'), explode('_', $name));
        return implode('_', $pieces);
    }

    public function styledName($name)
    {
        $pieces  = array_map(array($this, 'pluralToSingular'), explode('_', $name));
        $name    = $this->separatorToCamelCase(implode('_', $pieces), '_');
        return ucfirst($name);
    }

    public function composed($left, $right)
    {
        $left  = $this->singularToPlural($left);
        $right = $this->singularToPlural($right);

        return "{$left}_{$right}";
    }
}
