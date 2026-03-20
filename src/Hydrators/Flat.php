<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use Respect\Data\CollectionIterator;
use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Composite;
use Respect\Data\Collections\Filtered;
use Respect\Data\EntityFactory;
use SplObjectStorage;

use function array_pop;
use function array_reverse;
use function count;
use function is_array;

/**
 * Decomposes a flat row into multiple entity instances using PK boundaries.
 *
 * Subclasses define how column names are resolved from the raw data format.
 */
abstract class Flat extends Base
{
    /** @return SplObjectStorage<object, Collection>|false */
    public function hydrate(
        mixed $raw,
        Collection $collection,
        EntityFactory $entityFactory,
    ): SplObjectStorage|false {
        if (!$raw || !is_array($raw)) {
            return false;
        }

        /** @var SplObjectStorage<object, Collection> $entities */
        $entities = new SplObjectStorage();
        $entitiesInstances = $this->buildEntitiesInstances($collection, $entities, $entityFactory);

        if (!$entitiesInstances) {
            return false;
        }

        $entityInstance = array_pop($entitiesInstances);

        foreach (array_reverse($raw, true) as $col => $value) {
            $columnName = $this->resolveColumnName($col, $raw);
            $primaryName = $entityFactory->style->identifier(
                (string) $entities[$entityInstance]->name,
            );

            $entityFactory->set(
                /** @phpstan-ignore argument.type */
                $entityInstance,
                $columnName,
                $value,
            );

            if ($primaryName != $columnName && !$this->isEntityBoundary($col, $raw)) {
                continue;
            }

            $entityInstance = array_pop($entitiesInstances);
        }

        $entities = $this->resolveTypedEntities($entities, $entityFactory);

        if ($entities->count() > 1) {
            $this->wireRelationships($entities, $entityFactory);
        }

        return $entities;
    }

    /** Resolve the column name for a given reference (numeric index, namespaced key, etc.) */
    abstract protected function resolveColumnName(mixed $reference, mixed $raw): string;

    /** Check if this column is the last one for the current entity (table boundary without PK) */
    protected function isEntityBoundary(mixed $col, mixed $raw): bool
    {
        return false;
    }

    /**
     * @param SplObjectStorage<object, Collection> $entities
     *
     * @return SplObjectStorage<object, Collection>
     */
    private function resolveTypedEntities(
        SplObjectStorage $entities,
        EntityFactory $entityFactory,
    ): SplObjectStorage {
        /** @var SplObjectStorage<object, Collection> $resolved */
        $resolved = new SplObjectStorage();

        foreach ($entities as $entity) {
            $coll = $entities[$entity];
            $entityName = $coll->resolveEntityName($entityFactory, $entity);
            $defaultName = (string) $coll->name;

            if ($entityName === $defaultName) {
                $resolved[$entity] = $coll;

                continue;
            }

            $resolved[$entityFactory->hydrate($entity, $entityName)] = $coll;
        }

        return $resolved;
    }

    /**
     * @param SplObjectStorage<object, Collection> $entities
     *
     * @return array<int, object>
     */
    private function buildEntitiesInstances(
        Collection $collection,
        SplObjectStorage $entities,
        EntityFactory $entityFactory,
    ): array {
        $entitiesInstances = [];

        foreach (CollectionIterator::recursive($collection) as $c) {
            if ($c->name === null || ($c instanceof Filtered && !$c->filters)) {
                continue;
            }

            $entityInstance = $entityFactory->createByName($c->name);

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
}
