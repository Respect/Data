<?php

declare(strict_types=1);

namespace Respect\Data;

use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Composite;
use Respect\Data\Collections\Filtered;
use SplObjectStorage;

use function count;

abstract class AbstractMapper
{
    /** @var SplObjectStorage<object, true> */
    protected SplObjectStorage $new;

    /** @var SplObjectStorage<object, Collection> */
    protected SplObjectStorage $tracked;

    /** @var SplObjectStorage<object, true> */
    protected SplObjectStorage $changed;

    /** @var SplObjectStorage<object, true> */
    protected SplObjectStorage $removed;

    /** @var array<string, Collection> */
    private array $collections = [];

    public Styles\Stylable $style { get => $this->entityFactory->style; }

    public function __construct(
        public readonly EntityFactory $entityFactory = new EntityFactory(),
    ) {
        $this->tracked  = new SplObjectStorage();
        $this->changed  = new SplObjectStorage();
        $this->removed  = new SplObjectStorage();
        $this->new      = new SplObjectStorage();
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
        $next = $onCollection->next;
        if ($onCollection instanceof Filtered && $next !== null) {
            $this->persist($object, $next);

            return true;
        }

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
        $collection->mapper = $this;
        $this->collections[$alias] = $collection;
    }

    /** @param SplObjectStorage<object, Collection> $entities */
    protected function postHydrate(SplObjectStorage $entities): void
    {
        $entitiesClone = clone $entities;

        foreach ($entities as $instance) {
            foreach ($this->entityFactory->extractProperties($instance) as $field => $v) {
                if (!$this->style->isRemoteIdentifier($field)) {
                    continue;
                }

                foreach ($entitiesClone as $sub) {
                    $this->tryHydration($entities, $sub, $field, $v);
                }

                $this->entityFactory->set($instance, $field, $v);
            }
        }
    }

    /**
     * @param SplObjectStorage<object, Collection> $entities
     *
     * @return array<int, object>
     */
    protected function buildEntitiesInstances(
        Collection $collection,
        SplObjectStorage $entities,
    ): array {
        $entitiesInstances = [];

        foreach (CollectionIterator::recursive($collection) as $c) {
            if (!$c instanceof Collection) {
                continue;
            }

            if ($c instanceof Filtered && !$c->filters) {
                continue;
            }

            $entityInstance = $this->entityFactory->createByName((string) $c->name);

            if ($c instanceof Composite) {
                $compositionCount = count($c->compositions);
                for ($i = 0; $i < $compositionCount; $i++) {
                    $entitiesInstances[] = $entityInstance;
                }
            }

            $entities[$entityInstance] = $c;
            $entitiesInstances[] = $entityInstance;
        }

        return $entitiesInstances;
    }

    /** @param SplObjectStorage<object, Collection> $entities */
    private function tryHydration(SplObjectStorage $entities, object $sub, string $field, mixed &$v): void
    {
        $tableName = (string) $entities[$sub]->name;
        $primaryName = $this->style->identifier($tableName);

        if (
            $tableName !== $this->style->remoteFromIdentifier($field)
                || $this->entityFactory->get($sub, $primaryName) != $v
        ) {
            return;
        }

        $v = $sub;
    }

    public function __get(string $name): Collection
    {
        if (isset($this->collections[$name])) {
            return $this->collections[$name];
        }

        $coll = new Collection($name);
        $coll->mapper = $this;

        return $coll;
    }

    public function __isset(string $alias): bool
    {
        return isset($this->collections[$alias]);
    }

    public function __set(string $alias, Collection $collection): void
    {
        $this->registerCollection($alias, $collection);
    }

    /** @param array<int, mixed> $children */
    public function __call(string $name, array $children): Collection
    {
        $collection = Collection::__callstatic($name, $children);
        $collection->mapper = $this;

        return $collection;
    }
}
