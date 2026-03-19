<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use Respect\Data\Collections\Collection;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrator;
use SplObjectStorage;

use function get_object_vars;
use function is_object;

/** Hydrates entities from a nested structure (object with sub-objects keyed by collection name) */
final class Nested implements Hydrator
{
    /** @return SplObjectStorage<object, Collection>|false */
    public function hydrate(
        mixed $raw,
        Collection $collection,
        EntityFactory $entityFactory,
    ): SplObjectStorage|false {
        if (!is_object($raw)) {
            return false;
        }

        /** @var SplObjectStorage<object, Collection> $entities */
        $entities = new SplObjectStorage();
        $entity = $entityFactory->hydrate($raw, $collection->resolveEntityName($entityFactory, $raw));
        $entities[$entity] = $collection;

        $this->hydrateRelated($raw, $collection, $entityFactory, $entities);

        return $entities;
    }

    /** @param SplObjectStorage<object, Collection> $entities */
    private function hydrateRelated(
        object $raw,
        Collection $collection,
        EntityFactory $entityFactory,
        SplObjectStorage $entities,
    ): void {
        if ($collection->next !== null) {
            $this->hydrateChild($raw, $collection->next, $entityFactory, $entities);
        }

        foreach ($collection->children as $child) {
            $this->hydrateChild($raw, $child, $entityFactory, $entities);
        }
    }

    /** @param SplObjectStorage<object, Collection> $entities */
    private function hydrateChild(
        object $parentRaw,
        Collection $child,
        EntityFactory $entityFactory,
        SplObjectStorage $entities,
    ): void {
        $key = $child->name;
        if ($key === null) {
            return;
        }

        $vars = get_object_vars($parentRaw);
        if (!isset($vars[$key]) || !is_object($vars[$key])) {
            return;
        }

        $childRaw = $vars[$key];
        $entity = $entityFactory->hydrate($childRaw, $child->resolveEntityName($entityFactory, $childRaw));
        $entities[$entity] = $child;

        $this->hydrateRelated($childRaw, $child, $entityFactory, $entities);
    }
}
