<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

final class Filtered extends Collection
{
    public static function __callStatic(string $name, array $children): static
    {
        $collection = new static();
        $collection->extra('filters', []);

        return $collection->__call($name, $children);
    }

    public static function by(string $name): Collection
    {
        $collection = new Collection();
        $collection->extra('filters', func_get_args());

        return $collection;
    }
}
