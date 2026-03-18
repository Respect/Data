<?php

declare(strict_types=1);

namespace Respect\Data;

use Respect\Data\Collections\Collection;

use function array_filter;
use function array_values;
use function assert;
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
        $name = (string) $collection->getName();
        $rows = $this->tables[$name] ?? [];
        $condition = $collection->getCondition();
        $style = $this->getStyle();

        if ($condition !== null && $condition !== []) {
            $pk = $style->identifier($name);
            $pkValue = is_array($condition) ? reset($condition) : $condition;
            $rows = array_values(array_filter(
                $rows,
                static fn(array $row): bool => isset($row[$pk]) && $row[$pk] == $pkValue,
            ));
        }

        if ($rows === []) {
            return false;
        }

        $row = $rows[0];
        $factory = $this->entityFactory;
        $entity = $factory->createByName($name);

        foreach ($row as $key => $value) {
            $factory->set($entity, $key, $value);
        }

        if ($collection->hasMore()) {
            $this->resolveRelations($entity, $collection);
        }

        $this->markTracked($entity, $collection);

        return $entity;
    }

    /** @return array<int, mixed> */
    public function fetchAll(Collection $collection, mixed $extra = null): array
    {
        $name = (string) $collection->getName();
        $rows = $this->tables[$name] ?? [];
        $condition = $collection->getCondition();
        $style = $this->getStyle();

        if ($condition !== null && $condition !== []) {
            $pk = $style->identifier($name);
            $pkValue = is_array($condition) ? reset($condition) : $condition;
            $rows = array_values(array_filter(
                $rows,
                static fn(array $row): bool => isset($row[$pk]) && $row[$pk] == $pkValue,
            ));
        }

        $entities = [];

        $factory = $this->entityFactory;

        foreach ($rows as $row) {
            $entity = $factory->createByName($name);

            foreach ($row as $key => $value) {
                $factory->set($entity, $key, $value);
            }

            if ($collection->hasMore()) {
                $this->resolveRelations($entity, $collection);
            }

            $this->markTracked($entity, $collection);
            $entities[] = $entity;
        }

        return $entities;
    }

    public function flush(): void
    {
        $factory = $this->entityFactory;

        foreach ($this->new as $entity) {
            $collection = $this->tracked[$entity];
            assert($collection instanceof Collection);
            $tableName = (string) $collection->getName();
            $pk = $this->getStyle()->identifier($tableName);
            $row = $factory->extractProperties($entity);

            if (!isset($row[$pk])) {
                ++$this->lastInsertId;
                $factory->set($entity, $pk, $this->lastInsertId);
                $row[$pk] = $this->lastInsertId;
            }

            $this->tables[$tableName][] = $row;
        }

        foreach ($this->changed as $entity) {
            if ($this->new->offsetExists($entity)) {
                continue;
            }

            if ($this->removed->offsetExists($entity)) {
                continue;
            }

            $collection = $this->tracked[$entity];
            assert($collection instanceof Collection);
            $tableName = (string) $collection->getName();
            $pk = $this->getStyle()->identifier($tableName);
            $pkValue = $factory->get($entity, $pk);
            $row = $factory->extractProperties($entity);

            foreach ($this->tables[$tableName] as $index => $existing) {
                if (isset($existing[$pk]) && $existing[$pk] == $pkValue) {
                    $this->tables[$tableName][$index] = $row;

                    break;
                }
            }
        }

        foreach ($this->removed as $entity) {
            $collection = $this->tracked[$entity];
            assert($collection instanceof Collection);
            $tableName = (string) $collection->getName();
            $pk = $this->getStyle()->identifier($tableName);
            $pkValue = $factory->get($entity, $pk);

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

        $this->reset();
    }

    private function resolveRelations(object $entity, Collection $collection): void
    {
        $style = $this->getStyle();
        $factory = $this->entityFactory;
        $next = $collection->getNext();

        if ($next !== null) {
            $nextName = (string) $next->getName();
            $fkCol = $style->remoteIdentifier($nextName);
            $fkValue = $factory->get($entity, $fkCol);

            if ($fkValue !== null) {
                $childEntity = $this->findRelatedEntity($nextName, $fkValue, $next);

                if ($childEntity !== null) {
                    $factory->set($entity, $fkCol, $childEntity);
                }
            }
        }

        foreach ($collection->getChildren() as $child) {
            $childName = (string) $child->getName();
            $fkCol = $style->remoteIdentifier($childName);
            $fkValue = $factory->get($entity, $fkCol);

            if ($fkValue === null) {
                continue;
            }

            $childEntity = $this->findRelatedEntity($childName, $fkValue, $child);

            if ($childEntity === null) {
                continue;
            }

            $factory->set($entity, $fkCol, $childEntity);
        }
    }

    private function findRelatedEntity(string $tableName, mixed $fkValue, Collection $collection): object|null
    {
        $style = $this->getStyle();
        $factory = $this->entityFactory;
        $pk = $style->identifier($tableName);
        $rows = $this->tables[$tableName] ?? [];

        foreach ($rows as $row) {
            if (!isset($row[$pk]) || $row[$pk] != $fkValue) {
                continue;
            }

            $childEntity = $factory->createByName($tableName);

            foreach ($row as $key => $value) {
                $factory->set($childEntity, $key, $value);
            }

            if ($collection->hasMore()) {
                $this->resolveRelations($childEntity, $collection);
            }

            $this->markTracked($childEntity, $collection);

            return $childEntity;
        }

        return null;
    }
}
