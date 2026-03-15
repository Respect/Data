<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

final class Mix extends Collection
{
    public static function __callStatic(string $name, array $children): static
    {
        $collection = new static();
        $collection->extra('mixins', []);

        return $collection->__call($name, $children);
    }

    public static function with(mixed $mixins): Collection
    {
        $collection = new Collection();
        $collection->extra('mixins', $mixins);

        return $collection;
    }
}
