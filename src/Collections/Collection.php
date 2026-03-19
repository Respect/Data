<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use ArrayAccess;
use Respect\Data\AbstractMapper;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrator;
use RuntimeException;

use function is_array;
use function is_scalar;

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

    /** @param array<mixed>|scalar|null $condition */
    public function __construct(
        public private(set) string|null $name = null,
        public private(set) array|int|float|string|bool|null $condition = [],
    ) {
    }

    /** @param array<mixed>|scalar|null $condition */
    public static function using(array|int|float|string|bool|null $condition): static
    {
        return new static(condition: $condition);
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

    /** @param array<int, mixed> $children */
    public static function __callStatic(string $name, array $children): static
    {
        $collection = new static();

        return $collection->__call($name, $children);
    }

    public function __get(string $name): static
    {
        $mapper = $this->findMapper();
        if ($mapper !== null && isset($mapper->$name)) {
            return $this->stack(clone $mapper->__get($name));
        }

        return $this->stack(new self($name));
    }

    /** @param array<int, mixed> $children */
    public function __call(string $name, array $children): static
    {
        if (!isset($this->name)) {
            $this->name = $name;
            foreach ($children as $child) {
                if ($child instanceof Collection) {
                    $this->addChild($child);
                } elseif (is_array($child) || is_scalar($child) || $child === null) {
                    $this->condition = $child;
                }
            }

            return $this;
        }

        return $this->stack((new Collection())->__call($name, $children));
    }
}
