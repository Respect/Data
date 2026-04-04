<?php

declare(strict_types=1);

namespace Respect\Data;

use SplObjectStorage;

use function count;
use function ctype_digit;
use function is_int;
use function is_scalar;
use function is_string;

abstract class AbstractMapper
{
    /** @var SplObjectStorage<object, Scope> Maps entity → source Scope */
    protected SplObjectStorage $tracked;

    /** @var SplObjectStorage<object, string> Maps entity → 'insert'|'update'|'delete' */
    protected SplObjectStorage $pending;

    /** @var array<string, array<int|string, object>> Identity-indexed map: [scopeName][idValue] → entity */
    protected array $identityMap = [];

    public EntityFactory $entityFactory { get => $this->hydrator->entityFactory; }

    public Styles\Stylable $style { get => $this->entityFactory->style; }

    public function __construct(
        public readonly Hydrator $hydrator,
    ) {
        $this->tracked = new SplObjectStorage();
        $this->pending = new SplObjectStorage();
    }

    abstract public function flush(): void;

    abstract public function fetch(Scope $scope, mixed $extra = null): mixed;

    /** @return array<int, mixed> */
    abstract public function fetchAll(Scope $scope, mixed $extra = null): array;

    public function reset(): void
    {
        $this->pending = new SplObjectStorage();
    }

    public function clearIdentityMap(): void
    {
        $this->identityMap = [];
    }

    public function persist(object $object, Scope $onScope): object
    {
        if ($this->isTracked($object)) {
            $currentOp = $this->pending[$object] ?? null;
            if ($currentOp !== 'insert') {
                $this->pending[$object] = 'update';
            }

            return $object;
        }

        $merged = $this->tryMergeWithIdentityMap($object, $onScope);
        if ($merged !== null) {
            return $merged;
        }

        $this->pending[$object] = 'insert';
        $this->markTracked($object, $onScope);
        $this->registerInIdentityMap($object, $onScope);

        return $object;
    }

    public function remove(object $object, Scope $fromScope): void
    {
        $this->pending[$object] = 'delete';

        if ($this->isTracked($object)) {
            return;
        }

        $this->markTracked($object, $fromScope);
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

    public function markTracked(object $entity, Scope $scope): void
    {
        $this->tracked[$entity] = $scope;
    }

    public function isTracked(object $entity): bool
    {
        return $this->tracked->offsetExists($entity);
    }

    protected function registerInIdentityMap(object $entity, Scope $coll): void
    {
        $idValue = $this->entityIdValue($entity, $coll->name);
        if ($idValue === null) {
            return;
        }

        $this->identityMap[$coll->name][$idValue] = $entity;
    }

    protected function evictFromIdentityMap(object $entity, Scope $coll): void
    {
        $idValue = $this->entityIdValue($entity, $coll->name);
        if ($idValue === null) {
            return;
        }

        unset($this->identityMap[$coll->name][$idValue]);
    }

    protected function findInIdentityMap(Scope $scope): object|null
    {
        if (!is_scalar($scope->filter) || $scope->hasChildren) {
            return null;
        }

        $condition = $this->normalizeIdValue($scope->filter);
        if ($condition === null) {
            return null;
        }

        return $this->identityMap[$scope->name][$condition] ?? null;
    }

    private function tryMergeWithIdentityMap(object $entity, Scope $coll): object|null
    {
        $entityId = $this->entityIdValue($entity, $coll->name);
        $idValue = $entityId ?? $this->normalizeIdValue($coll->filter);

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

    /** @param array<string, mixed> $arguments */
    public function __call(string $name, array $arguments): Scope
    {
        return new Scope($name, ...$arguments); // @phpstan-ignore argument.type
    }
}
