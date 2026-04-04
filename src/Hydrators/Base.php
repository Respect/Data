<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use DomainException;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrator;
use Respect\Data\Scope;
use SplObjectStorage;

/** Base hydrator providing scope-tree entity wiring */
abstract class Base implements Hydrator
{
    public function __construct(
        public readonly EntityFactory $entityFactory = new EntityFactory(),
    ) {
    }

    public function hydrate(
        mixed $raw,
        Scope $scope,
    ): object|false {
        $entities = $this->hydrateAll($raw, $scope);
        if ($entities === false) {
            return false;
        }

        foreach ($entities as $entity) {
            if ($entities[$entity] === $scope) {
                return $entity;
            }
        }

        throw new DomainException(
            'Hydration produced no entity for scope "' . $scope->name . '"',
        );
    }

    /** @param SplObjectStorage<object, Scope> $entities */
    protected function wireRelationships(SplObjectStorage $entities): void
    {
        $style = $this->entityFactory->style;
        $others = clone $entities;

        foreach ($entities as $entity) {
            $scope = $entities[$entity];

            foreach ($others as $other) {
                if ($other === $entity) {
                    continue;
                }

                $otherScope = $others[$other];
                if ($otherScope->parent !== $scope) {
                    continue;
                }

                $relationName = $style->relationProperty(
                    $style->remoteIdentifier($otherScope->name),
                );

                if ($relationName === null) {
                    continue;
                }

                $id = $this->entityFactory->get($other, $style->identifier($otherScope->name));
                if ($id === null) {
                    continue;
                }

                $this->entityFactory->set($entity, $relationName, $other);
            }
        }
    }
}
