<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use Respect\Data\EntityFactory;

use function is_array;
use function is_string;

final class Typed extends Collection
{
    /**
     * @param list<Collection> $with
     * @param array<scalar, mixed>|scalar|null $filter
     */
    public function __construct(
        string $name,
        public private(set) readonly string $type = '',
        array $with = [],
        array|int|float|string|bool|null $filter = null,
        bool $required = false,
    ) {
        parent::__construct($name, $with, $filter, $required);
    }

    /**
     * @param object|array<string, mixed> $row
     *
     * @return class-string
     */
    public function resolveEntityClass(EntityFactory $factory, object|array $row): string
    {
        $name = is_array($row) ? ($row[$this->type] ?? null) : $factory->get($row, $this->type);

        return $factory->resolveClass(is_string($name) ? $name : (string) $this->name);
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

        return ['type' => $this->type] + $base;
    }

    /** @param array<int, string> $arguments */
    public static function __callStatic(string $name, array $arguments): static
    {
        return new static($name, ...$arguments);
    }
}
