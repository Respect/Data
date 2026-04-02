<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

final class Composite extends Collection
{
    public const string COMPOSITION_MARKER = '_WITH_';

    /**
     * @param array<string, list<string>> $compositions
     * @param list<Collection> $with
     * @param array<scalar, mixed>|scalar|null $filter
     */
    public function __construct(
        string $name,
        public private(set) readonly array $compositions = [],
        array $with = [],
        array|int|float|string|bool|null $filter = null,
        bool $required = false,
    ) {
        parent::__construct($name, $with, $filter, $required);
    }

    /**
     * @param list<Collection> $with
     *
     * @return array<string, mixed>
     */
    protected function deriveArgs(
        array $with = [],
        array|int|float|string|bool|null $filter = null,
        bool|null $required = null,
    ): array {
        $base = parent::deriveArgs($with, $filter, $required);

        return ['compositions' => $this->compositions] + $base;
    }

    /** @param array<int, array<string, list<string>>> $arguments */
    public static function __callStatic(string $name, array $arguments): static
    {
        return new static($name, ...$arguments);
    }
}
