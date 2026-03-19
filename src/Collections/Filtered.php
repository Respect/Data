<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use function array_values;

final class Filtered extends Collection
{
    /** Fetch only the entity identifier (primary key, document ID, etc.) */
    public const string IDENTIFIER_ONLY = '*';

    // phpcs:ignore PSR2.Classes.PropertyDeclaration
    public bool $identifierOnly { get => $this->filters === [self::IDENTIFIER_ONLY]; }

    /**
     * @param list<string> $filters
     * @param array<mixed>|scalar|null $condition
     */
    public function __construct(
        public private(set) readonly array $filters = [],
        string|null $name = null,
        array|int|float|string|bool|null $condition = [],
    ) {
        parent::__construct($name, $condition);
    }

    public static function by(string ...$names): static
    {
        return new static(filters: array_values($names));
    }

    /** @param array<int, mixed> $children */
    public static function __callStatic(string $name, array $children): static
    {
        $collection = new static();

        return $collection->__call($name, $children);
    }
}
