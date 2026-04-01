<?php

declare(strict_types=1);

namespace Respect\Data;

use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Filtered;
use SplObjectStorage;

use function array_flip;
use function array_intersect_key;
use function assert;
use function count;
use function is_int;
use function is_scalar;
use function is_string;

abstract class AbstractMapper
{
    /** @var SplObjectStorage<object, Collection> Maps entity → source Collection */
    protected SplObjectStorage $tracked;

    /** @var SplObjectStorage<object, string> Maps entity → 'insert'|'update'|'delete' */
    protected SplObjectStorage $pending;

    /** @var array<string, array<int|string, object>> Identity-indexed map: [collectionName][idValue] → entity */
    protected array $identityMap = [];

    /** @var array<string, Collection> */
    private array $collections = [];

    public Styles\Stylable $style { get => $this->entityFactory->style; }

    public function __construct(
        public readonly EntityFactory $entityFactory = new EntityFactory(),
    ) {
        $this->tracked = new SplObjectStorage();
        $this->pending = new SplObjectStorage();
    }

    abstract public function flush(): void;

    abstract public function fetch(Collection $collection, mixed $extra = null): mixed;

    /** @return array<int, mixed> */
    abstract public function fetchAll(Collection $collection, mixed $extra = null): array;

    public function reset(): void
    {
        $this->pending = new SplObjectStorage();
    }

    public function clearIdentityMap(): void
    {
        $this->identityMap = [];
    }

    public function trackedCount(): int
    {
        return count($this->tracked);
    }

    public function identityMapCount(): int
    {
        $total = 0;
        foreach ($this->identityMap as $entries) {
            $total += count($entries);
        }

        return $total;
    }

    public function markTracked(object $entity, Collection $collection): bool
    {
        $this->tracked[$entity] = $collection;

        return true;
    }

    public function persist(object $object, Collection $onCollection): object
    {
        $next = $onCollection->next;
        if ($onCollection instanceof Filtered && $next !== null) {
            $this->persist($object, $next);

            return $object;
        }

        if ($this->isTracked($object)) {
            $currentOp = $this->pending[$object] ?? null;
            if ($currentOp !== 'insert') {
                $this->pending[$object] = 'update';
            }

            return $object;
        }

        if ($onCollection->name !== null && $this->tryReplaceFromIdentityMap($object, $onCollection)) {
            return $object;
        }

        $this->pending[$object] = 'insert';
        $this->markTracked($object, $onCollection);

        return $object;
    }

    public function remove(object $object, Collection $fromCollection): bool
    {
        $this->pending[$object] = 'delete';

        if (!$this->isTracked($object)) {
            $this->markTracked($object, $fromCollection);
        }

        return true;
    }

    public function isTracked(object $entity): bool
    {
        return $this->tracked->offsetExists($entity);
    }

    public function replaceTracked(object $old, object $new, Collection $onCollection): void
    {
        $op = $this->pending[$old] ?? 'update';
        $this->tracked->offsetUnset($old);
        $this->pending->offsetUnset($old);
        $this->evictFromIdentityMap($old, $onCollection);

        $this->markTracked($new, $onCollection);
        $this->registerInIdentityMap($new, $onCollection);
        $this->pending[$new] = $op;
    }

    public function registerCollection(string $alias, Collection $collection): void
    {
        $collection->mapper = $this;
        $this->collections[$alias] = $collection;
    }

    abstract protected function defaultHydrator(Collection $collection): Hydrator;

    /**
     * @param array<string, mixed> $columns
     *
     * @return array<string, mixed>
     */
    protected function filterColumns(array $columns, Collection $collection): array
    {
        if (
            !$collection instanceof Filtered
            || !$collection->filters
            || $collection->identifierOnly
            || $collection->name === null
        ) {
            return $columns;
        }

        $id = $this->style->identifier($collection->name);

        return array_intersect_key($columns, array_flip([...$collection->filters, $id]));
    }

    protected function resolveHydrator(Collection $collection): Hydrator
    {
        return $collection->hydrator ?? $this->defaultHydrator($collection);
    }

    protected function registerInIdentityMap(object $entity, Collection $coll): void
    {
        if ($coll->name === null) {
            return;
        }

        $idValue = $this->entityIdValue($entity, $coll->name);
        if ($idValue === null) {
            return;
        }

        $this->identityMap[$coll->name][$idValue] = $entity;
    }

    protected function evictFromIdentityMap(object $entity, Collection $coll): void
    {
        if ($coll->name === null) {
            return;
        }

        $idValue = $this->entityIdValue($entity, $coll->name);
        if ($idValue === null) {
            return;
        }

        unset($this->identityMap[$coll->name][$idValue]);
    }

    protected function findInIdentityMap(Collection $collection): object|null
    {
        if ($collection->name === null || !is_scalar($collection->condition) || $collection->more) {
            return null;
        }

        $condition = $collection->condition;
        if (!is_int($condition) && !is_string($condition)) {
            return null;
        }

        return $this->identityMap[$collection->name][$condition] ?? null;
    }

    private function tryReplaceFromIdentityMap(object $entity, Collection $coll): bool
    {
        assert($coll->name !== null);
        $entityId = $this->entityIdValue($entity, $coll->name);
        $idValue = $entityId;

        if ($idValue === null && is_scalar($coll->condition)) {
            $idValue = $coll->condition;
        }

        if ($idValue === null || (!is_int($idValue) && !is_string($idValue))) {
            return false;
        }

        $existing = $this->identityMap[$coll->name][$idValue] ?? null;
        if ($existing === null || $existing === $entity) {
            return false;
        }

        if ($entityId === null) {
            $idName = $this->style->identifier($coll->name);
            $this->entityFactory->set($entity, $idName, $idValue);
        }

        $this->tracked->offsetUnset($existing);
        $this->pending->offsetUnset($existing);
        $this->evictFromIdentityMap($existing, $coll);
        $this->markTracked($entity, $coll);
        $this->registerInIdentityMap($entity, $coll);
        $this->pending[$entity] = 'update';

        return true;
    }

    private function entityIdValue(object $entity, string $collName): int|string|null
    {
        $idValue = $this->entityFactory->get($entity, $this->style->identifier($collName));

        return is_int($idValue) || is_string($idValue) ? $idValue : null;
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

    /** @param list<Collection|array<scalar, mixed>|scalar|null> $arguments */
    public function __call(string $name, array $arguments): Collection
    {
        if (isset($this->collections[$name])) {
            $collection = clone $this->collections[$name];
            $collection->mapper = $this;

            return $collection->with(...$arguments);
        }

        $collection = Collection::__callstatic($name, $arguments);
        $collection->mapper = $this;

        return $collection;
    }
}
