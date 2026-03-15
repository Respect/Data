<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

final class Typed extends Collection
{
    public static function by(string $type): Collection
    {
        $collection = new Collection();
        $collection->extra('type', $type);

        return $collection;
    }

    /** @param array<int, mixed> $children */
    public static function __callStatic(string $name, array $children): static
    {
        $collection = new static();
        $collection->extra('type', '');

        return $collection->__call($name, $children);
    }
}
