<?php

declare(strict_types=1);

namespace Respect\Data;

use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Filtered;
use SplObjectStorage;

use function array_flip;
use function array_intersect_key;
use function count;
use function ctype_digit;
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
        $connectsTo = $onCollection->connectsTo;
        if ($onCollection instanceof Filtered && $connectsTo !== null) {
            $this->persist($object, $connectsTo);

            return $object;
        }

        if ($this->isTracked($object)) {
            $currentOp = $this->pending[$object] ?? null;
            if ($currentOp !== 'insert') {
                $this->pending[$object] = 'update';
            }

            return $object;
        }

        $merged = $this->tryMergeWithIdentityMap($object, $onCollection);
        if ($merged !== null) {
            return $merged;
        }

        $this->pending[$object] = 'insert';
        $this->markTracked($object, $onCollection);
        $this->registerInIdentityMap($object, $onCollection);

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

    public function registerCollection(string $alias, Collection $collection): void
    {
        $collection->bindMapper($this);
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
        if ($collection->name === null || !is_scalar($collection->condition) || $collection->hasMore) {
            return null;
        }

        $condition = $this->normalizeIdValue($collection->condition);
        if ($condition === null) {
            return null;
        }

        return $this->identityMap[$collection->name][$condition] ?? null;
    }

    private function tryMergeWithIdentityMap(object $entity, Collection $coll): object|null
    {
        if ($coll->name === null) {
            return null;
        }

        $entityId = $this->entityIdValue($entity, $coll->name);
        $idValue = $entityId ?? $this->normalizeIdValue($coll->condition);

        if ($idValue === null) {
            return null;
        }

        $existing = $this->identityMap[$coll->name][$idValue] ?? null;
        if ($existing === null || $existing === $entity) {
            return null;
        }

        if ($entityId === null) {
            $idName = $this->style->identifier($coll->name);
            $this->entityFactory->set($entity, $idName, $idValue);
        }

        $op = $this->pending[$existing] ?? 'update';

        if ($this->entityFactory->isReadOnly($existing)) {
            $merged = $this->entityFactory->mergeEntities($existing, $entity);

            if ($merged !== $existing) {
                $this->tracked->offsetUnset($existing);
                $this->pending->offsetUnset($existing);
                $this->evictFromIdentityMap($existing, $coll);
                $this->markTracked($merged, $coll);
                $this->registerInIdentityMap($merged, $coll);
            }

            $this->pending[$merged] = $op;

            return $merged;
        }

        foreach ($this->entityFactory->extractProperties($entity) as $prop => $value) {
            $this->entityFactory->set($existing, $prop, $value);
        }

        if (!$this->isTracked($existing)) {
            $this->markTracked($existing, $coll);
        }

        $this->pending[$existing] = $op;

        return $existing;
    }

    private function entityIdValue(object $entity, string $collName): int|string|null
    {
        return $this->normalizeIdValue(
            $this->entityFactory->get($entity, $this->style->identifier($collName)),
        );
    }

    private function normalizeIdValue(mixed $value): int|string|null
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return ctype_digit($value) ? (int) $value : $value;
        }

        return null;
    }

    public function __get(string $name): Collection
    {
        if (isset($this->collections[$name])) {
            return $this->collections[$name];
        }

        $coll = new Collection($name);

        return $coll->bindMapper($this);
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

            return $collection->bindMapper($this)->with(...$arguments);
        }

        return Collection::__callstatic($name, $arguments)->bindMapper($this);
    }
}
