<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

final class Composite extends Collection
{
    /** @var array<string, list<string>> */
    private array $compositions = [];

    /** @param array<string, list<string>> $compositions */
    public static function with(array $compositions): static
    {
        $collection = new static();
        $collection->compositions = $compositions;

        return $collection;
    }

    /** @return array<string, list<string>> */
    public function getCompositions(): array
    {
        return $this->compositions;
    }

    /** @param array<int, mixed> $children */
    public static function __callStatic(string $name, array $children): static
    {
        $collection = new static();

        return $collection->__call($name, $children);
    }
}
