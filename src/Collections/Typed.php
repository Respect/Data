<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use Respect\Data\EntityFactory;

use function is_string;

final class Typed extends Collection
{
    private string $type = '';

    public static function by(string $type): static
    {
        $collection = new static();
        $collection->type = $type;

        return $collection;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function resolveEntityName(EntityFactory $factory, object $row): string
    {
        $name = $factory->get($row, $this->type);

        return is_string($name) ? $name : ($this->getName() ?? '');
    }

    /** @param array<int, mixed> $children */
    public static function __callStatic(string $name, array $children): static
    {
        $collection = new static();

        return $collection->__call($name, $children);
    }
}
