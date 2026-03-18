<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use function array_values;

final class Filtered extends Collection
{
    /** Fetch only the entity identifier (primary key, document ID, etc.) */
    public const string IDENTIFIER_ONLY = '*';

    /** @var list<string> */
    private array $filters = [];

    public static function by(string ...$names): static
    {
        $collection = new static();
        $collection->filters = array_values($names);

        return $collection;
    }

    /** @return list<string> */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function isIdentifierOnly(): bool
    {
        return $this->filters === [self::IDENTIFIER_ONLY];
    }

    /** @param array<int, mixed> $children */
    public static function __callStatic(string $name, array $children): static
    {
        $collection = new static();

        return $collection->__call($name, $children);
    }
}
