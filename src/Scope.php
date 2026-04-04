<?php

declare(strict_types=1);

namespace Respect\Data;

class Scope
{
    public private(set) Scope|null $parent = null;

    /** @var list<Scope> */
    public private(set) array $with;

    public bool $hasChildren { get => !empty($this->with); }

    /**
     * @param list<Scope> $with
     * @param array<scalar, mixed>|scalar|null $filter
     */
    public function __construct(
        public readonly string $name,
        array $with = [],
        public readonly array|int|float|string|bool|null $filter = null,
        public readonly bool $required = false,
    ) {
        $this->with = $this->adoptChildren($with);
    }

    /**
     * @param list<Scope> $children
     *
     * @return list<Scope>
     */
    private function adoptChildren(array $children): array
    {
        $adopted = [];
        foreach ($children as $child) {
            $c = clone $child;
            $c->parent = $this;
            $adopted[] = $c;
        }

        return $adopted;
    }

    /** @param array<int, mixed> $arguments */
    public static function __callStatic(string $name, array $arguments): static
    {
        return new static($name, ...$arguments);
    }

    public function __clone(): void
    {
        $this->with = $this->adoptChildren($this->with);
        $this->parent = null;
    }
}
