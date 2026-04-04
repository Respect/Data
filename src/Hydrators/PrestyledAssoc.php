<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use DomainException;
use Respect\Data\Scope;
use Respect\Data\ScopeIterator;
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
    /** @var array<string, Scope> */
    private array $scopeMap = [];

    private Scope|null $cachedScope = null;

    /** @return SplObjectStorage<object, Scope>|false */
    public function hydrateAll(
        mixed $raw,
        Scope $scope,
    ): SplObjectStorage|false {
        if (!$raw || !is_array($raw)) {
            return false;
        }

        $scopeMap = $this->buildScopeMap($scope);

        /** @var array<string, array<string, mixed>> $grouped */
        $grouped = [];
        foreach ($raw as $alias => $value) {
            [$prefix, $prop] = explode('__', $alias, 2);
            $grouped[$prefix][$prop] = $value;
        }

        /** @var SplObjectStorage<object, Scope> $entities */
        $entities = new SplObjectStorage();
        /** @var array<string, object> $instances */
        $instances = [];

        foreach ($grouped as $prefix => $props) {
            if (!isset($scopeMap[$prefix])) {
                throw new DomainException('Unknown column prefix "' . $prefix . '" in hydration row');
            }

            $basePrefix = $prefix;

            if (!isset($instances[$basePrefix])) {
                $matched = $scopeMap[$basePrefix];
                $class = $this->entityFactory->resolveClass((string) $matched->name);
                $instances[$basePrefix] = $this->entityFactory->create($class);
                $entities[$instances[$basePrefix]] = $matched;
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

    /** @return array<string, Scope> */
    private function buildScopeMap(Scope $scope): array
    {
        if ($this->cachedScope === $scope) {
            return $this->scopeMap;
        }

        $this->scopeMap = [];
        foreach (ScopeIterator::recursive($scope) as $spec => $c) {
            $this->scopeMap[$spec] = $c;
        }

        $this->cachedScope = $scope;

        return $this->scopeMap;
    }
}
