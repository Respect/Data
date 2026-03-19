<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use Respect\Data\EntityFactory;

use function is_string;

final class Typed extends Collection
{
    public function __construct(
        string $name,
        public private(set) readonly string $type = '',
    ) {
        parent::__construct($name);
    }

    public function resolveEntityName(EntityFactory $factory, object $row): string
    {
        $name = $factory->get($row, $this->type);

        return is_string($name) ? $name : ($this->name ?? '');
    }

    /** @param array<int, string> $arguments */
    public static function __callStatic(string $name, array $arguments): static
    {
        return new static($name, ...$arguments);
    }
}
