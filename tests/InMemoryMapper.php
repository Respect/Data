<?php

declare(strict_types=1);

namespace Respect\Data;

use Respect\Data\Collections\Collection;
use stdClass;

use function array_filter;
use function array_key_exists;
use function array_values;
use function assert;
use function class_exists;
use function get_object_vars;
use function is_array;
use function reset;

final class InMemoryMapper extends AbstractMapper
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $tables = [];

    private int $lastInsertId = 1000;

    public string $entityNamespace = '\\';

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
        $entity = $this->createEntity($name);

        foreach ($row as $key => $value) {
            $entity->{$key} = $value;
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

        foreach ($rows as $row) {
            $entity = $this->createEntity($name);

            foreach ($row as $key => $value) {
                $entity->{$key} = $value;
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
        foreach ($this->new as $entity) {
            $collection = $this->tracked[$entity];
            assert($collection instanceof Collection);
            $tableName = (string) $collection->getName();
            $pk = $this->getStyle()->identifier($tableName);
            $row = get_object_vars($entity);

            if (!isset($row[$pk])) {
                ++$this->lastInsertId;
                $entity->{$pk} = $this->lastInsertId;
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
            $pkValue = $entity->{$pk};
            $row = get_object_vars($entity);

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
            $pkValue = $entity->{$pk};

            foreach ($this->tables[$tableName] as $index => $existing) {
                if (isset($existing[$pk]) && $existing[$pk] == $pkValue) {
                    unset($this->tables[$tableName][$index]);
                    $this->tables[$tableName] = array_values($this->tables[$tableName]);

                    break;
                }
            }
        }

        $this->reset();
    }

    private function resolveRelations(object $entity, Collection $collection): void
    {
        $style = $this->getStyle();
        $next = $collection->getNext();

        if ($next !== null) {
            $nextName = (string) $next->getName();
            $fkCol = $style->remoteIdentifier($nextName);

            if (array_key_exists($fkCol, get_object_vars($entity))) {
                $fkValue = $entity->{$fkCol};
                $childEntity = $this->findRelatedEntity($nextName, $fkValue, $next);

                if ($childEntity !== null) {
                    $entity->{$fkCol} = $childEntity;
                }
            }
        }

        foreach ($collection->getChildren() as $child) {
            $childName = (string) $child->getName();
            $fkCol = $style->remoteIdentifier($childName);

            if (!array_key_exists($fkCol, get_object_vars($entity))) {
                continue;
            }

            $fkValue = $entity->{$fkCol};
            $childEntity = $this->findRelatedEntity($childName, $fkValue, $child);

            if ($childEntity === null) {
                continue;
            }

            $entity->{$fkCol} = $childEntity;
        }
    }

    private function findRelatedEntity(string $tableName, mixed $fkValue, Collection $collection): object|null
    {
        $style = $this->getStyle();
        $pk = $style->identifier($tableName);
        $rows = $this->tables[$tableName] ?? [];

        foreach ($rows as $row) {
            if (!isset($row[$pk]) || $row[$pk] != $fkValue) {
                continue;
            }

            $childEntity = $this->createEntity($tableName);

            foreach ($row as $key => $value) {
                $childEntity->{$key} = $value;
            }

            if ($collection->hasMore()) {
                $this->resolveRelations($childEntity, $collection);
            }

            $this->markTracked($childEntity, $collection);

            return $childEntity;
        }

        return null;
    }

    private function createEntity(string $entityName): object
    {
        $className = $this->getStyle()->styledName($entityName);
        $fullClass = $this->entityNamespace . $className;

        if (class_exists($fullClass)) {
            return new $fullClass();
        }

        return new stdClass();
    }
}
