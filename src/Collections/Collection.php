<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use ArrayAccess;
use Respect\Data\AbstractMapper;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrator;
use RuntimeException;

/** @implements ArrayAccess<string, Collection> */
class Collection implements ArrayAccess
{
    public private(set) bool $required = true;

    public AbstractMapper|null $mapper = null;

    public Hydrator|null $hydrator = null;

    public private(set) Collection|null $parent = null;

    public private(set) Collection|null $next = null;

    private Collection|null $last = null;

    /** @var Collection[] */
    public private(set) array $children = [];

    public bool $hasChildren { get => !empty($this->children); }

    public bool $more { get => $this->hasChildren || $this->next !== null; }

    /** @var array<scalar, mixed>|scalar|null */
    public private(set) array|int|float|string|bool|null $condition = [];

    /** @param (Collection|array<scalar, mixed>|scalar|null) ...$args */
    public function __construct(
        public private(set) string|null $name = null,
        self|array|int|float|string|bool|null ...$args,
    ) {
        $this->with(...$args);
    }

    public function addChild(Collection $child): void
    {
        $clone = clone $child;
        $clone->required = false;
        $clone->parent = $this;
        $this->children[] = $clone;
    }

    public function persist(object $object): bool
    {
        return $this->resolveMapper()->persist($object, $this);
    }

    public function remove(object $object): bool
    {
        return $this->resolveMapper()->remove($object, $this);
    }

    public function fetch(mixed $extra = null): mixed
    {
        return $this->resolveMapper()->fetch($this, $extra);
    }

    public function fetchAll(mixed $extra = null): mixed
    {
        return $this->resolveMapper()->fetchAll($this, $extra);
    }

    public function resolveEntityName(EntityFactory $factory, object $row): string
    {
        return $this->name ?? '';
    }

    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    public function offsetGet(mixed $condition): mixed
    {
        $tail = $this->last ?? $this;
        $tail->condition = $condition;

        return $this;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // no-op
    }

    public function offsetUnset(mixed $offset): void
    {
        // no-op
    }

    public function hydrateFrom(Hydrator $hydrator): static
    {
        $this->hydrator = $hydrator;

        return $this;
    }

    public function stack(Collection $collection): static
    {
        $tail = $this->last ?? $this;
        $tail->setNext($collection);
        $this->last = $collection->last ?? $collection;

        return $this;
    }

    /** @param self|array<scalar, mixed>|scalar|null ...$arguments */
    public function with(self|array|int|float|string|bool|null ...$arguments): static
    {
        foreach ($arguments as $arg) {
            $arg instanceof Collection ? $this->addChild($arg) : $this->condition = $arg;
        }

        return $this;
    }

    private function findMapper(): AbstractMapper|null
    {
        $node = $this;
        while ($node !== null) {
            if ($node->mapper !== null) {
                return $node->mapper;
            }

            $node = $node->parent;
        }

        return null;
    }

    private function resolveMapper(): AbstractMapper
    {
        return $this->findMapper() ?? throw new RuntimeException();
    }

    private function setNext(Collection $collection): void
    {
        $collection->parent = $this;
        $this->next = $collection;
    }

    /** @param array<int, mixed> $arguments */
    public static function __callStatic(string $name, array $arguments): static
    {
        return new static($name, ...$arguments);
    }

    public function __get(string $name): static
    {
        $mapper = $this->findMapper();
        if ($mapper !== null && isset($mapper->$name)) {
            return $this->stack(clone $mapper->__get($name));
        }

        return $this->stack(new self($name));
    }

    /** @param list<self|array<scalar, mixed>|scalar|null> $children */
    public function __call(string $name, array $children): static
    {
        if (!isset($this->name)) {
            $this->name = $name;

            return $this->with(...$children);
        }

        return $this->stack((new Collection())->__call($name, $children));
    }

    public function __clone(): void
    {
        if ($this->next !== null) {
            $this->next = clone $this->next;
            $this->next->parent = $this;
        }

        $clonedChildren = [];

        foreach ($this->children as $child) {
            $cloned = clone $child;
            $cloned->parent = $this;
            $clonedChildren[] = $cloned;
        }

        $this->children = $clonedChildren;
        $this->parent = null;

        if ($this->last === null) {
            return;
        }

        $node = $this;

        while ($node->next !== null) {
            $node = $node->next;
        }

        $this->last = $node !== $this ? $node : null;
    }
}
