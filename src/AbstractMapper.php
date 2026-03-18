<?php

declare(strict_types=1);

namespace Respect\Data;

use Respect\Data\Collections\Collection;
use SplObjectStorage;

use function assert;

abstract class AbstractMapper
{
    /** @var SplObjectStorage<object, mixed> */
    protected SplObjectStorage $new;

    /** @var SplObjectStorage<object, mixed> */
    protected SplObjectStorage $tracked;

    /** @var SplObjectStorage<object, mixed> */
    protected SplObjectStorage $changed;

    /** @var SplObjectStorage<object, mixed> */
    protected SplObjectStorage $removed;

    /** @var array<string, Collection> */
    protected array $collections = [];

    public function __construct(
        public readonly EntityFactory $entityFactory = new EntityFactory(),
    ) {
        $this->tracked  = new SplObjectStorage();
        $this->changed  = new SplObjectStorage();
        $this->removed  = new SplObjectStorage();
        $this->new      = new SplObjectStorage();
    }

    public function getStyle(): Styles\Stylable
    {
        return $this->entityFactory->style;
    }

    abstract public function flush(): void;

    abstract public function fetch(Collection $collection, mixed $extra = null): mixed;

    /** @return array<int, mixed> */
    abstract public function fetchAll(Collection $collection, mixed $extra = null): array;

    public function reset(): void
    {
        $this->changed = new SplObjectStorage();
        $this->removed = new SplObjectStorage();
        $this->new = new SplObjectStorage();
    }

    public function markTracked(object $entity, Collection $collection): bool
    {
        $this->tracked[$entity] = $collection;

        return true;
    }

    public function persist(object $object, Collection $onCollection): bool
    {
        $this->changed[$object] = true;

        if ($this->isTracked($object)) {
            return true;
        }

        $this->new[$object] = true;
        $this->markTracked($object, $onCollection);

        return true;
    }

    public function remove(object $object, Collection $fromCollection): bool
    {
        $this->changed[$object] = true;
        $this->removed[$object] = true;

        if ($this->isTracked($object)) {
            return true;
        }

        $this->markTracked($object, $fromCollection);

        return true;
    }

    public function isTracked(object $entity): bool
    {
        return $this->tracked->offsetExists($entity);
    }

    public function registerCollection(string $alias, Collection $collection): void
    {
        $collection->setMapper($this);
        $this->collections[$alias] = $collection;
    }

    public function __get(string $name): Collection
    {
        if (isset($this->collections[$name])) {
            return $this->collections[$name];
        }

        $coll = new Collection($name);
        $coll->setMapper($this);

        return $coll;
    }

    public function __isset(string $alias): bool
    {
        return isset($this->collections[$alias]);
    }

    public function __set(string $alias, mixed $collection): void
    {
        assert($collection instanceof Collection);
        $this->registerCollection($alias, $collection);
    }

    /** @param array<int, mixed> $children */
    public function __call(string $name, array $children): Collection
    {
        $collection = Collection::__callstatic($name, $children);
        $collection->setMapper($this);

        return $collection;
    }
}
