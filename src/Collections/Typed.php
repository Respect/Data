<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use Respect\Data\EntityFactory;

use function is_string;

final class Typed extends Collection
{
    /** @param array<mixed>|scalar|null $condition */
    public function __construct(
        public private(set) readonly string $type = '',
        string|null $name = null,
        array|int|float|string|bool|null $condition = [],
    ) {
        parent::__construct($name, $condition);
    }

    public static function by(string $type): static
    {
        return new static(type: $type);
    }

    public function resolveEntityName(EntityFactory $factory, object $row): string
    {
        $name = $factory->get($row, $this->type);

        return is_string($name) ? $name : ($this->name ?? '');
    }

    /** @param array<int, mixed> $children */
    public static function __callStatic(string $name, array $children): static
    {
        $collection = new static();

        return $collection->__call($name, $children);
    }
}
