<?php

declare(strict_types=1);

namespace Respect\Data;

use Respect\Data\Collections\Collection;
use SplObjectStorage;

abstract class AbstractMapper
{
    protected ?Styles\Stylable $style = null;
    protected SplObjectStorage $new;
    protected SplObjectStorage $tracked;
    protected SplObjectStorage $changed;
    protected SplObjectStorage $removed;
    /** @var array<string, Collection> */
    protected array $collections = [];

    abstract protected function createStatement(Collection $fromCollection, mixed $withExtra = null): mixed;

    protected function parseHydrated(SplObjectStorage $hydrated): mixed
    {
        $this->tracked->addAll($hydrated);
        $hydrated->rewind();

        return $hydrated->current();
    }

    public function getStyle(): Styles\Stylable
    {
        if (null === $this->style) {
            $this->setStyle(new Styles\Standard());
        }

        return $this->style;
    }

    public function setStyle(Styles\Stylable $style): static
    {
        $this->style = $style;

        return $this;
    }

    public function __construct()
    {
        $this->tracked  = new SplObjectStorage();
        $this->changed  = new SplObjectStorage();
        $this->removed  = new SplObjectStorage();
        $this->new      = new SplObjectStorage();
    }

    abstract public function flush(): void;

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

    public function fetch(Collection $fromCollection, mixed $withExtra = null): mixed
    {
        $statement = $this->createStatement($fromCollection, $withExtra);
        $hydrated = $this->fetchHydrated($fromCollection, $statement);
        if (!$hydrated) {
            return false;
        }

        return $this->parseHydrated($hydrated);
    }

    public function fetchAll(Collection $fromCollection, mixed $withExtra = null): array
    {
        $statement = $this->createStatement($fromCollection, $withExtra);
        $entities = [];

        while ($hydrated = $this->fetchHydrated($fromCollection, $statement)) {
            $entities[] = $this->parseHydrated($hydrated);
        }

        return $entities;
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
        return $this->tracked->contains($entity);
    }

    protected function fetchHydrated(Collection $collection, mixed $statement): SplObjectStorage|false
    {
        if (!$collection->hasMore()) {
            return $this->fetchSingle($collection, $statement);
        } else {
            return $this->fetchMulti($collection, $statement);
        }
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
        $this->registerCollection($alias, $collection);
    }

    public function __call(string $name, array $children): Collection
    {
        $collection = Collection::__callstatic($name, $children);
        $collection->setMapper($this);

        return $collection;
    }

    public function registerCollection(string $alias, Collection $collection): void
    {
        $collection->setMapper($this);
        $this->collections[$alias] = $collection;
    }
}
