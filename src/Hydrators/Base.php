<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Typed;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrator;
use SplObjectStorage;

/** Base hydrator providing collection-tree entity wiring */
abstract class Base implements Hydrator
{
    /** @param SplObjectStorage<object, Collection> $entities */
    protected function wireRelationships(SplObjectStorage $entities, EntityFactory $entityFactory): void
    {
        $style = $entityFactory->style;
        $others = clone $entities;

        foreach ($entities as $entity) {
            $coll = $entities[$entity];

            foreach ($others as $other) {
                if ($other === $entity) {
                    continue;
                }

                $otherColl = $others[$other];
                if ($otherColl->parent !== $coll || $otherColl->name === null) {
                    continue;
                }

                $relationName = $style->relationProperty(
                    $style->remoteIdentifier($otherColl->name),
                );

                if ($relationName === null) {
                    continue;
                }

                $id = $entityFactory->get($other, $style->identifier($otherColl->name));
                if ($id === null) {
                    continue;
                }

                $entityFactory->set($entity, $relationName, $other);
            }
        }
    }

    /**
     * @param object|array<mixed, mixed> $row
     *
     * @return class-string
     */
    protected function resolveEntityClass(
        Collection $collection,
        EntityFactory $entityFactory,
        object|array $row,
    ): string {
        if ($collection instanceof Typed) {
            return $collection->resolveEntityClass($entityFactory, $row);
        }

        return $entityFactory->resolveClass((string) $collection->name);
    }
}
