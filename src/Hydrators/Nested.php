<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use Respect\Data\Collections\Collection;
use Respect\Data\EntityFactory;
use SplObjectStorage;

use function is_array;

/** Hydrates entities from a nested associative array keyed by collection name */
final class Nested extends Base
{
    /** @return SplObjectStorage<object, Collection>|false */
    public function hydrate(
        mixed $raw,
        Collection $collection,
        EntityFactory $entityFactory,
    ): SplObjectStorage|false {
        if (!is_array($raw)) {
            return false;
        }

        /** @var SplObjectStorage<object, Collection> $entities */
        $entities = new SplObjectStorage();

        $this->hydrateNode($raw, $collection, $entityFactory, $entities);

        if ($entities->count() > 1) {
            $this->wireRelationships($entities, $entityFactory);
        }

        return $entities;
    }

    /**
     * @param array<mixed, mixed> $data
     * @param SplObjectStorage<object, Collection> $entities
     */
    private function hydrateNode(
        array $data,
        Collection $collection,
        EntityFactory $entityFactory,
        SplObjectStorage $entities,
    ): void {
        $entity = $entityFactory->create(
            $this->resolveEntityClass($collection, $entityFactory, $data),
        );

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $entityFactory->set($entity, $key, $value);
        }

        $entities[$entity] = $collection;

        if ($collection->next !== null) {
            $this->hydrateChild($data, $collection->next, $entityFactory, $entities);
        }

        foreach ($collection->children as $child) {
            $this->hydrateChild($data, $child, $entityFactory, $entities);
        }
    }

    /**
     * @param array<string, mixed> $parentData
     * @param SplObjectStorage<object, Collection> $entities
     */
    private function hydrateChild(
        array $parentData,
        Collection $child,
        EntityFactory $entityFactory,
        SplObjectStorage $entities,
    ): void {
        $key = $child->name;
        if ($key === null || !isset($parentData[$key]) || !is_array($parentData[$key])) {
            return;
        }

        /** @var array<string, mixed> $childData */
        $childData = $parentData[$key];
        $this->hydrateNode($childData, $child, $entityFactory, $entities);
    }
}
