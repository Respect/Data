<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

final class Composite extends Collection
{
    public const string COMPOSITION_MARKER = '_WITH_';

    /** @param array<string, list<string>> $compositions */
    public function __construct(
        string $name,
        public private(set) readonly array $compositions = [],
    ) {
        parent::__construct($name);
    }

    /** @param array<int, array<string, list<string>>> $arguments */
    public static function __callStatic(string $name, array $arguments): static
    {
        return new static($name, ...$arguments);
    }
}
