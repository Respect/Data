<?php

declare(strict_types=1);

namespace Respect\Data;

use Respect\Data\Collections\Collection;
use SplObjectStorage;
use stdClass;

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
        $row = $this->findRow($name, $collection->getCondition());

        if ($row === null) {
            return false;
        }

        $rowObject = $this->rowToObject($row);
        $entityName = $collection->resolveEntityName($this->entityFactory, $rowObject);
        $entity = $this->entityFactory->createByName($entityName);

        foreach ($row as $key => $value) {
            $this->entityFactory->set($entity, $key, $value);
        }

        if ($collection->hasMore()) {
            /** @var SplObjectStorage<object, Collection> $entities */
            $entities = new SplObjectStorage();
            $entities[$entity] = $collection;
            $this->fetchRelated($entity, $collection, $entities);
            $this->postHydrate($entities);
        }

        $this->markTracked($entity, $collection);

        return $entity;
    }

    /** @return array<int, mixed> */
    public function fetchAll(Collection $collection, mixed $extra = null): array
    {
        $name = (string) $collection->getName();
        $rows = $this->findRows($name, $collection->getCondition());
        $result = [];

        foreach ($rows as $row) {
            $rowObject = $this->rowToObject($row);
            $entityName = $collection->resolveEntityName($this->entityFactory, $rowObject);
            $entity = $this->entityFactory->createByName($entityName);

            foreach ($row as $key => $value) {
                $this->entityFactory->set($entity, $key, $value);
            }

            if ($collection->hasMore()) {
                /** @var SplObjectStorage<object, Collection> $entities */
                $entities = new SplObjectStorage();
                $entities[$entity] = $collection;
                $this->fetchRelated($entity, $collection, $entities);
                $this->postHydrate($entities);
            }

            $this->markTracked($entity, $collection);
            $result[] = $entity;
        }

        return $result;
    }

    public function flush(): void
    {
        foreach ($this->new as $entity) {
            $collection = $this->tracked[$entity];
            assert($collection instanceof Collection);
            $tableName = (string) $collection->getName();
            $pk = $this->getStyle()->identifier($tableName);
            $row = $this->entityFactory->extractProperties($entity);

            if (!isset($row[$pk])) {
                ++$this->lastInsertId;
                $this->entityFactory->set($entity, $pk, $this->lastInsertId);
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
            $pkValue = $this->entityFactory->get($entity, $pk);
            $row = $this->entityFactory->extractProperties($entity);

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

        $this->reset();
    }

    /** @param SplObjectStorage<object, Collection> $entities */
    private function fetchRelated(object $parent, Collection $collection, SplObjectStorage $entities): void
    {
        $next = $collection->getNext();

        if ($next !== null) {
            $this->fetchRelatedCollection($parent, $next, $entities);
        }

        foreach ($collection->getChildren() as $child) {
            $this->fetchRelatedCollection($parent, $child, $entities);
        }
    }

    /** @param SplObjectStorage<object, Collection> $entities */
    private function fetchRelatedCollection(
        object $parent,
        Collection $related,
        SplObjectStorage $entities,
    ): void {
        $relatedName = (string) $related->getName();
        $fkCol = $this->getStyle()->remoteIdentifier($relatedName);
        $fkValue = $this->entityFactory->get($parent, $fkCol);

        if ($fkValue === null) {
            return;
        }

        $pk = $this->getStyle()->identifier($relatedName);
        $row = $this->findRowByPk($relatedName, $pk, $fkValue);

        if ($row === null) {
            return;
        }

        $rowObject = $this->rowToObject($row);
        $entityName = $related->resolveEntityName($this->entityFactory, $rowObject);
        $childEntity = $this->entityFactory->createByName($entityName);

        foreach ($row as $key => $value) {
            $this->entityFactory->set($childEntity, $key, $value);
        }

        $entities[$childEntity] = $related;
        $this->markTracked($childEntity, $related);

        if (!$related->hasMore()) {
            return;
        }

        $this->fetchRelated($childEntity, $related, $entities);
    }

    /** @return array<string, mixed>|null */
    private function findRow(string $table, mixed $condition): array|null
    {
        $rows = $this->findRows($table, $condition);

        return $rows[0] ?? null;
    }

    /** @return list<array<string, mixed>> */
    private function findRows(string $table, mixed $condition): array
    {
        $rows = $this->tables[$table] ?? [];

        if ($condition === null || $condition === []) {
            return $rows;
        }

        $pk = $this->getStyle()->identifier($table);
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

    /** @param array<string, mixed> $row */
    private function rowToObject(array $row): object
    {
        $obj = new stdClass();
        foreach ($row as $key => $value) {
            $obj->{$key} = $value;
        }

        return $obj;
    }
}
