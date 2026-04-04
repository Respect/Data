<?php

declare(strict_types=1);

namespace Respect\Data;

use function array_filter;
use function array_merge;
use function array_values;
use function is_array;

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

    public function fetch(Scope $scope, mixed $extra = null): mixed
    {
        if ($extra === null) {
            $cached = $this->findInIdentityMap($scope);
            if ($cached !== null) {
                return $cached;
            }
        }

        $row = $this->findRow((string) $scope->name, $scope->filter);

        return $row !== null ? $this->hydrateRow($row, $scope) : false;
    }

    /** @return array<int, mixed> */
    public function fetchAll(Scope $scope, mixed $extra = null): array
    {
        $rows = $this->findRows((string) $scope->name, $scope->filter);
        $result = [];

        foreach ($rows as $row) {
            $entity = $this->hydrateRow($row, $scope);
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
            $scope = $this->tracked[$entity];
            $tableName = (string) $scope->name;
            $id = $this->style->identifier($tableName);

            match ($op) {
                'insert' => $this->insertEntity($entity, $scope, $tableName, $id),
                'update' => $this->updateEntity($entity, $scope, $tableName, $id),
                'delete' => $this->deleteEntity($entity, $tableName, $id),
                default  => null,
            };

            if ($op === 'delete') {
                $this->evictFromIdentityMap($entity, $scope);
            } else {
                $this->registerInIdentityMap($entity, $scope);
            }
        }

        $this->reset();
    }

    private function insertEntity(object $entity, Scope $scope, string $tableName, string $id): void
    {
        $row = $this->entityFactory->extractColumns($entity);

        if (!isset($row[$id])) {
            ++$this->lastInsertId;
            $this->entityFactory->set($entity, $id, $this->lastInsertId);
            $row[$id] = $this->lastInsertId;
        }

        $this->tables[$tableName][] = $row;
    }

    private function updateEntity(object $entity, Scope $scope, string $tableName, string $id): void
    {
        $idValue = $this->entityFactory->get($entity, $id);
        $row = $this->entityFactory->extractColumns($entity);

        foreach ($this->tables[$tableName] as $index => $existing) {
            if (isset($existing[$id]) && $existing[$id] == $idValue) {
                $this->tables[$tableName][$index] = array_merge($existing, $row);

                break;
            }
        }
    }

    private function deleteEntity(object $entity, string $tableName, string $id): void
    {
        if (!isset($this->tables[$tableName])) {
            return;
        }

        $idValue = $this->entityFactory->get($entity, $id);
        $rows = $this->tables[$tableName];

        foreach ($rows as $index => $existing) {
            if (isset($existing[$id]) && $existing[$id] == $idValue) {
                unset($rows[$index]);
                /** @var list<array<string, mixed>> $reindexed */
                $reindexed = array_values($rows);
                $this->tables[$tableName] = $reindexed;

                break;
            }
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrateRow(array $row, Scope $scope): object|false
    {
        $this->attachRelated($row, $scope);

        $entities = $this->hydrator->hydrateAll($row, $scope);
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
    private function attachRelated(array &$parentRow, Scope $scope): void
    {
        foreach ($scope->with as $child) {
            $this->attachChild($parentRow, $child);
        }
    }

    /** @param array<string, mixed> $parentRow */
    private function attachChild(array &$parentRow, Scope $child): void
    {
        $childName = (string) $child->name;
        $refValue = $parentRow[$this->style->remoteIdentifier($childName)] ?? null;

        if ($refValue === null) {
            return;
        }

        $id = $this->style->identifier($childName);
        $childRow = $this->findRowById($childName, $id, $refValue);

        if ($childRow === null) {
            return;
        }

        if ($child->hasChildren) {
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

        if (is_array($condition)) {
            return array_values(array_filter(
                $rows,
                static fn(array $row): bool => self::matchesCondition($row, $condition),
            ));
        }

        $id = $this->style->identifier($table);

        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => isset($row[$id]) && $row[$id] == $condition,
        ));
    }

    /**
     * @param array<string, mixed> $row
     * @param array<mixed, mixed> $condition
     */
    private static function matchesCondition(array $row, array $condition): bool
    {
        foreach ($condition as $column => $value) {
            if (!isset($row[$column]) || $row[$column] != $value) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    private function findRowById(string $table, string $id, mixed $idValue): array|null
    {
        foreach ($this->tables[$table] ?? [] as $row) {
            if (isset($row[$id]) && $row[$id] == $idValue) {
                return $row;
            }
        }

        return null;
    }
}
