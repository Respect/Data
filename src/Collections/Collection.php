<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use Respect\Data\AbstractMapper;
use ArrayAccess;

class Collection implements ArrayAccess
{
    protected bool $required = true;
    protected ?AbstractMapper $mapper = null;
    protected ?string $name = null;
    protected mixed $condition = [];
    protected ?Collection $parent = null;
    protected ?Collection $next = null;
    protected ?Collection $last = null;
    /** @var Collection[] */
    protected array $children = [];
    /** @var array<string, mixed> */
    protected array $extras = [];

    public function extra(string $name, mixed $specs): static
    {
        $this->extras[$name] = $specs;

        return $this;
    }

    public function getExtra(string $name): mixed
    {
        if ($this->have($name)) {
            return $this->extras[$name];
        }

        return null;
    }

    public function have(string $name): bool
    {
        return isset($this->extras[$name]);
    }

    public static function using(mixed $condition): static
    {
        $collection = new static();
        $collection->setCondition($condition);

        return $collection;
    }

    public static function __callStatic(string $name, array $children): static
    {
        $collection = new static();

        return $collection->__call($name, $children);
    }

    public function __construct(?string $name = null, mixed $condition = [])
    {
        $this->name = $name;
        $this->condition = $condition;
        $this->last = $this;
    }

    public function __get(string $name): static
    {
        if (isset($this->mapper) && isset($this->mapper->$name)) {
            return $this->stack(clone $this->mapper->$name);
        }

        return $this->stack(new self($name));
    }

    public function __call(string $name, array $children): static
    {
        if (!isset($this->name)) {
            $this->name = $name;
            foreach ($children as $child) {
                if ($child instanceof Collection) {
                    $this->addChild($child);
                } else {
                    $this->setCondition($child);
                }
            }

            return $this;
        }

        $collection = self::__callStatic($name, $children);

        return $this->stack($collection);
    }

    public function addChild(Collection $child): void
    {
        $clone = clone $child;
        $clone->setRequired(false);
        $clone->setMapper($this->mapper);
        $clone->setParent($this);
        $this->children[] = $clone;
    }

    public function persist(object $object): mixed
    {
        if (!$this->mapper) {
            throw new \RuntimeException();
        }

        return $this->mapper->persist($object, $this);
    }

    public function remove(object $object): mixed
    {
        if (!$this->mapper) {
            throw new \RuntimeException();
        }

        return $this->mapper->remove($object, $this);
    }

    public function fetch(mixed $extra = null): mixed
    {
        if (!$this->mapper) {
            throw new \RuntimeException();
        }

        return $this->mapper->fetch($this, $extra);
    }

    public function fetchAll(mixed $extra = null): mixed
    {
        if (!$this->mapper) {
            throw new \RuntimeException();
        }

        return $this->mapper->fetchAll($this, $extra);
    }

    /** @return Collection[] */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function getCondition(): mixed
    {
        return $this->condition;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getNext(): ?Collection
    {
        return $this->next;
    }

    public function getParentName(): ?string
    {
        return $this->parent ? $this->parent->getName() : null;
    }

    public function getNextName(): ?string
    {
        return $this->next ? $this->next->getName() : null;
    }

    public function hasChildren(): bool
    {
        return !empty($this->children);
    }

    public function hasMore(): bool
    {
        return $this->hasChildren() || $this->hasNext();
    }

    public function hasNext(): bool
    {
        return !is_null($this->next);
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function offsetExists(mixed $offset): bool
    {
        return false;
    }

    public function offsetGet(mixed $condition): mixed
    {
        $this->last->condition = $condition;

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

    public function setCondition(mixed $condition): void
    {
        $this->condition = $condition;
    }

    public function setMapper(?AbstractMapper $mapper = null): void
    {
        foreach ($this->children as $child) {
            $child->setMapper($mapper);
        }
        $this->mapper = $mapper;
    }

    public function setParent(Collection $parent): void
    {
        $this->parent = $parent;
    }

    public function setNext(Collection $collection): void
    {
        $collection->setParent($this);
        $collection->setMapper($this->mapper);
        $this->next = $collection;
    }

    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }

    public function stack(Collection $collection): static
    {
        $this->last->setNext($collection);
        $this->last = $collection;

        return $this;
    }
}
