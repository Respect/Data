<?php

declare(strict_types=1);

namespace Respect\Data;

use Respect\Data\Collections\Collection;
use Respect\Data\Hydrators\Nested;

use function array_filter;
use function array_merge;
use function array_values;
use function is_array;
use function reset;

final class InMemoryMapper extends AbstractMapper
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $tables = [];

    private int $lastInsertId = 1000;

    /** @param list<array<string, mixed>> $rows */
    public function seed(string $table, array $rows): void
    {
        $this->tables[$table] = $rows;
    }

    public function fetch(Collection $collection, mixed $extra = null): mixed
    {
        if ($extra === null) {
            $cached = $this->findInIdentityMap($collection);
            if ($cached !== null) {
                return $cached;
            }
        }

        $row = $this->findRow((string) $collection->name, $collection->condition);

        return $row !== null ? $this->hydrateRow($row, $collection) : false;
    }

    /** @return array<int, mixed> */
    public function fetchAll(Collection $collection, mixed $extra = null): array
    {
        $rows = $this->findRows((string) $collection->name, $collection->condition);
        $result = [];

        foreach ($rows as $row) {
            $entity = $this->hydrateRow($row, $collection);
            if ($entity === false) {
                continue;
            }

            $result[] = $entity;
        }

        return $result;
    }

    public function flush(): void
    {
        foreach ($this->pending as $entity) {
            $op = $this->pending[$entity];
            $collection = $this->tracked[$entity];
            $tableName = (string) $collection->name;
            $pk = $this->style->identifier($tableName);

            match ($op) {
                'insert' => $this->insertEntity($entity, $collection, $tableName, $pk),
                'update' => $this->updateEntity($entity, $collection, $tableName, $pk),
                'delete' => $this->deleteEntity($entity, $tableName, $pk),
                default  => null,
            };

            if ($op === 'delete') {
                $this->evictFromIdentityMap($entity, $collection);
            } else {
                $this->registerInIdentityMap($entity, $collection);
            }
        }

        $this->reset();
    }

    protected function defaultHydrator(Collection $collection): Hydrator
    {
        return new Nested();
    }

    private function insertEntity(object $entity, Collection $collection, string $tableName, string $pk): void
    {
        $row = $this->filterColumns(
            $this->entityFactory->extractColumns($entity),
            $collection,
        );

        if (!isset($row[$pk])) {
            ++$this->lastInsertId;
            $this->entityFactory->set($entity, $pk, $this->lastInsertId);
            $row[$pk] = $this->lastInsertId;
        }

        $this->tables[$tableName][] = $row;
    }

    private function updateEntity(object $entity, Collection $collection, string $tableName, string $pk): void
    {
        $pkValue = $this->entityFactory->get($entity, $pk);
        $row = $this->filterColumns(
            $this->entityFactory->extractColumns($entity),
            $collection,
        );

        foreach ($this->tables[$tableName] as $index => $existing) {
            if (isset($existing[$pk]) && $existing[$pk] == $pkValue) {
                $this->tables[$tableName][$index] = array_merge($existing, $row);

                break;
            }
        }
    }

    private function deleteEntity(object $entity, string $tableName, string $pk): void
    {
        $pkValue = $this->entityFactory->get($entity, $pk);
        $rows = $this->tables[$tableName];

        foreach ($rows as $index => $existing) {
            if (isset($existing[$pk]) && $existing[$pk] == $pkValue) {
                unset($rows[$index]);
                /** @var list<array<string, mixed>> $reindexed */
                $reindexed = array_values($rows);
                $this->tables[$tableName] = $reindexed;

                break;
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrateRow(array $row, Collection $collection): object|false
    {
        $this->attachRelated($row, $collection);

        $entities = $this->resolveHydrator($collection)->hydrate($row, $collection, $this->entityFactory);
        if ($entities === false) {
            return false;
        }

        foreach ($entities as $entity) {
            $this->markTracked($entity, $entities[$entity]);
            $this->registerInIdentityMap($entity, $entities[$entity]);
        }

        $entities->rewind();

        return $entities->current();
    }

    /** @param array<string, mixed> $parentRow */
    private function attachRelated(array &$parentRow, Collection $collection): void
    {
        if ($collection->next !== null) {
            $this->attachChild($parentRow, $collection->next);
        }

        foreach ($collection->children as $child) {
            $this->attachChild($parentRow, $child);
        }
    }

    /** @param array<string, mixed> $parentRow */
    private function attachChild(array &$parentRow, Collection $child): void
    {
        $childName = (string) $child->name;
        $fkValue = $parentRow[$this->style->remoteIdentifier($childName)] ?? null;

        if ($fkValue === null) {
            return;
        }

        $pk = $this->style->identifier($childName);
        $childRow = $this->findRowByPk($childName, $pk, $fkValue);

        if ($childRow === null) {
            return;
        }

        if ($child->more) {
            $this->attachRelated($childRow, $child);
        }

        $parentRow[$childName] = $childRow;
    }

    /** @return array<string, mixed>|null */
    private function findRow(string $table, mixed $condition): array|null
    {
        return $this->findRows($table, $condition)[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    private function findRows(string $table, mixed $condition): array
    {
        $rows = $this->tables[$table] ?? [];

        if ($condition === null || $condition === []) {
            return $rows;
        }

        $pk = $this->style->identifier($table);
        $pkValue = is_array($condition) ? reset($condition) : $condition;

        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => isset($row[$pk]) && $row[$pk] == $pkValue,
        ));
    }

    /** @return array<string, mixed>|null */
    private function findRowByPk(string $table, string $pk, mixed $pkValue): array|null
    {
        foreach ($this->tables[$table] ?? [] as $row) {
            if (isset($row[$pk]) && $row[$pk] == $pkValue) {
                return $row;
            }
        }

        return null;
    }
}
