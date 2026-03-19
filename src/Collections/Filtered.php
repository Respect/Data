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

    /** @param list<string> $filters */
    public function __construct(
        string $name,
        public private(set) readonly array $filters = [],
    ) {
        parent::__construct($name);
    }

    /** @param array<scalar, string> $arguments */
    public static function __callStatic(string $name, array $arguments): static
    {
        return new static($name, array_values($arguments));
    }
}
