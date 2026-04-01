<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use DomainException;
use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Typed;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrator;
use SplObjectStorage;

/** Base hydrator providing collection-tree entity wiring */
abstract class Base implements Hydrator
{
    public function __construct(
        public readonly EntityFactory $entityFactory = new EntityFactory(),
    ) {
    }

    public function hydrate(
        mixed $raw,
        Collection $collection,
    ): object|false {
        $entities = $this->hydrateAll($raw, $collection);
        if ($entities === false) {
            return false;
        }

        foreach ($entities as $entity) {
            if ($entities[$entity] === $collection) {
                return $entity;
            }
        }

        throw new DomainException(
            'Hydration produced no entity for collection "' . $collection->name . '"',
        );
    }

    /** @param SplObjectStorage<object, Collection> $entities */
    protected function wireRelationships(SplObjectStorage $entities): void
    {
        $style = $this->entityFactory->style;
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

                $id = $this->entityFactory->get($other, $style->identifier($otherColl->name));
                if ($id === null) {
                    continue;
                }

                $this->entityFactory->set($entity, $relationName, $other);
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
        object|array $row,
    ): string {
        if ($collection instanceof Typed) {
            return $collection->resolveEntityClass($this->entityFactory, $row);
        }

        return $this->entityFactory->resolveClass((string) $collection->name);
    }
}
