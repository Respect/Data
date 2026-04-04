<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use Respect\Data\Scope;
use SplObjectStorage;

use function is_array;

/** Hydrates entities from a nested associative array keyed by scope name */
final class Nested extends Base
{
    /** @return SplObjectStorage<object, Scope>|false */
    public function hydrateAll(
        mixed $raw,
        Scope $scope,
    ): SplObjectStorage|false {
        if (!is_array($raw)) {
            return false;
        }

        /** @var SplObjectStorage<object, Scope> $entities */
        $entities = new SplObjectStorage();

        $this->hydrateNode($raw, $scope, $entities);

        if ($entities->count() > 1) {
            $this->wireRelationships($entities);
        }

        return $entities;
    }

    /**
     * @param array<mixed, mixed> $data
     * @param SplObjectStorage<object, Scope> $entities
     */
    private function hydrateNode(
        array $data,
        Scope $scope,
        SplObjectStorage $entities,
    ): void {
        $entity = $this->entityFactory->create(
            $this->entityFactory->resolveClass((string) $scope->name),
        );

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $this->entityFactory->set($entity, $key, $value);
        }

        $entities[$entity] = $scope;

        foreach ($scope->with as $child) {
            $this->hydrateChild($data, $child, $entities);
        }
    }

    /**
     * @param array<string, mixed> $parentData
     * @param SplObjectStorage<object, Scope> $entities
     */
    private function hydrateChild(
        array $parentData,
        Scope $child,
        SplObjectStorage $entities,
    ): void {
        $key = $child->name;
        if (!isset($parentData[$key]) || !is_array($parentData[$key])) {
            return;
        }

        /** @var array<string, mixed> $childData */
        $childData = $parentData[$key];
        $this->hydrateNode($childData, $child, $entities);
    }
}
