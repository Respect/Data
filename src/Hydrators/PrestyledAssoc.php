<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use DomainException;
use Respect\Data\CollectionIterator;
use Respect\Data\Collections\Collection;
use SplObjectStorage;

use function explode;
use function is_array;

/**
 * Hydrates associative rows whose keys are pre-styled as `specifier__styledProp`.
 *
 * This hydrator groups columns by their specifier prefix and
 * maps them directly to entities — no reverse iteration, boundary detection,
 * or entity stack needed.
 */
final class PrestyledAssoc extends Base
{
    /** @var array<string, Collection> */
    private array $collMap = [];

    private Collection|null $cachedCollection = null;

    /** @return SplObjectStorage<object, Collection>|false */
    public function hydrateAll(
        mixed $raw,
        Collection $collection,
    ): SplObjectStorage|false {
        if (!$raw || !is_array($raw)) {
            return false;
        }

        $collMap = $this->buildCollMap($collection);

        /** @var array<string, array<string, mixed>> $grouped */
        $grouped = [];
        foreach ($raw as $alias => $value) {
            [$prefix, $prop] = explode('__', $alias, 2);
            $grouped[$prefix][$prop] = $value;
        }

        /** @var SplObjectStorage<object, Collection> $entities */
        $entities = new SplObjectStorage();
        /** @var array<string, object> $instances */
        $instances = [];

        foreach ($grouped as $prefix => $props) {
            if (!isset($collMap[$prefix])) {
                throw new DomainException('Unknown column prefix "' . $prefix . '" in hydration row');
            }

            $basePrefix = $prefix;

            if (!isset($instances[$basePrefix])) {
                $coll = $collMap[$basePrefix];
                $class = $this->entityFactory->resolveClass((string) $coll->name);
                $instances[$basePrefix] = $this->entityFactory->create($class);
                $entities[$instances[$basePrefix]] = $coll;
            }

            $entity = $instances[$basePrefix];
            foreach ($props as $prop => $value) {
                $this->entityFactory->set($entity, $prop, $value, styled: true);
            }
        }

        if ($entities->count() > 1) {
            $this->wireRelationships($entities);
        }

        return $entities;
    }

    /** @return array<string, Collection> */
    private function buildCollMap(Collection $collection): array
    {
        if ($this->cachedCollection === $collection) {
            return $this->collMap;
        }

        $this->collMap = [];
        foreach (CollectionIterator::recursive($collection) as $spec => $c) {
            $this->collMap[$spec] = $c;
        }

        $this->cachedCollection = $collection;

        return $this->collMap;
    }
}
