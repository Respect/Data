<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

final class Composite extends Collection
{
    /**
     * @param array<string, list<string>> $compositions
     * @param array<mixed>|scalar|null $condition
     */
    public function __construct(
        public private(set) readonly array $compositions = [],
        string|null $name = null,
        array|int|float|string|bool|null $condition = [],
    ) {
        parent::__construct($name, $condition);
    }

    /** @param array<string, list<string>> $compositions */
    public static function with(array $compositions): static
    {
        return new static(compositions: $compositions);
    }

    /** @param array<int, mixed> $children */
    public static function __callStatic(string $name, array $children): static
    {
        $collection = new static();

        return $collection->__call($name, $children);
    }
}
