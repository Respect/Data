<?php

declare(strict_types=1);

namespace Respect\Data;

use DomainException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

use function array_key_exists;
use function class_exists;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_object;
use function is_scalar;
use function is_string;

/** Creates and manipulates entity objects using Style-based naming conventions */
class EntityFactory
{
    /** @var array<class-string, ReflectionClass<object>> */
    private array $classCache = [];

    /** @var array<class-string, array<string, ReflectionProperty>> */
    private array $propertyCache = [];

    /** @var array<string, class-string> */
    private array $resolveCache = [];

    /** @var array<string, array<string, true>> */
    private array $relationCache = [];

    /** @var array<string, array<string, string>> */
    private array $fieldCache = [];

    public function __construct(
        public readonly Styles\Stylable $style = new Styles\Standard(),
        private readonly string $entityNamespace = '\\',
    ) {
    }

    /** @return class-string */
    public function resolveClass(string $name): string
    {
        if (isset($this->resolveCache[$name])) {
            return $this->resolveCache[$name];
        }

        $entityName = $this->style->styledName($name);
        $entityClass = $this->entityNamespace . $entityName;

        if (!class_exists($entityClass)) {
            throw new DomainException('Entity class ' . $entityClass . ' not found for ' . $name);
        }

        return $this->resolveCache[$name] = $entityClass;
    }

    public function set(object $entity, string $prop, mixed $value, bool $styled = false): void
    {
        $styledProp = $styled ? $prop : $this->style->styledProperty($prop);
        $mirror = $this->reflectProperties($entity::class)[$styledProp] ?? null;

        if ($mirror === null) {
            return;
        }

        $coerced = $this->coerce($mirror, $value);

        if ($coerced === null && !($mirror->getType()?->allowsNull() ?? false)) {
            return;
        }

        if ($mirror->isReadOnly() && $mirror->isInitialized($entity)) {
            throw new ReadOnlyViolation(
                'Cannot modify readonly property ' . $entity::class . '::$' . $mirror->getName(),
            );
        }

        $mirror->setValue($entity, $coerced);
    }

    public function get(object $entity, string $prop): mixed
    {
        $styledProp = $this->style->styledProperty($prop);
        $mirror = $this->reflectProperties($entity::class)[$styledProp] ?? null;

        if ($mirror === null || !$mirror->isInitialized($entity)) {
            return null;
        }

        return $mirror->getValue($entity);
    }

    public function isReadOnly(object $entity): bool
    {
        return $this->reflectClass($entity::class)->isReadOnly();
    }

    /**
     * @param class-string<T> $class
     *
     * @return T
     *
     * @template T of object
     */
    public function create(string $class, mixed ...$properties): object
    {
        /** @phpstan-var T $entity */
        $entity = $this->reflectClass($class)->newInstanceWithoutConstructor();

        foreach ($properties as $prop => $value) {
            $this->set($entity, (string) $prop, $value);
        }

        return $entity;
    }

    public function mergeEntities(object $base, object $overlay): object
    {
        if ($base::class !== $overlay::class) {
            throw new DomainException(
                'Cannot merge entities of different classes: ' . $base::class . ' and ' . $overlay::class,
            );
        }

        $overlayProps = $this->extractProperties($overlay);
        $hasDifference = false;

        foreach ($overlayProps as $name => $value) {
            $mirror = $this->reflectProperties($base::class)[$name];

            if (!$mirror->isInitialized($base) || $mirror->getValue($base) !== $value) {
                $hasDifference = true;
                break;
            }
        }

        if (!$hasDifference) {
            return $base;
        }

        $clone = $this->reflectClass($base::class)->newInstanceWithoutConstructor();

        foreach ($this->reflectProperties($base::class) as $name => $prop) {
            if (array_key_exists($name, $overlayProps)) {
                $prop->setValue($clone, $overlayProps[$name]);
            } elseif ($prop->isInitialized($base)) {
                $prop->setValue($clone, $prop->getValue($base));
            }
        }

        return $clone;
    }

    /**
     * Extract persistable columns, resolving entity objects to their reference representations.
     *
     * @return array<string, mixed>
     */
    public function extractColumns(object $entity): array
    {
        $cols = $this->extractProperties($entity);
        $relations = $this->detectRelationProperties($entity::class);

        foreach ($cols as $key => $value) {
            if (!isset($relations[$key])) {
                continue;
            }

            $ref = $this->style->remoteIdentifier($key);

            if (is_object($value)) {
                $cols[$ref] = $this->get($value, $this->style->identifier($key));
            }

            unset($cols[$key]);
        }

        return $cols;
    }

    /** @return array<string, mixed> */
    public function extractProperties(object $entity): array
    {
        $props = [];

        foreach ($this->reflectProperties($entity::class) as $name => $prop) {
            if ($prop->isVirtual() || !$prop->isInitialized($entity) || $prop->getAttributes(NotPersistable::class)) {
                continue;
            }

            $props[$name] = $prop->getValue($entity);
        }

        return $props;
    }

    /**
     * Enumerate persistable fields for a collection, mapping DB column names to styled property names.
     *
     * @return array<string, string> DB column name → styled property name
     */
    public function enumerateFields(string $collectionName): array
    {
        if (isset($this->fieldCache[$collectionName])) {
            return $this->fieldCache[$collectionName];
        }

        $class = $this->resolveClass($collectionName);
        $relations = $this->detectRelationProperties($class);
        $fields = [];

        foreach ($this->reflectProperties($class) as $name => $prop) {
            if ($prop->isVirtual() || $prop->getAttributes(NotPersistable::class) || isset($relations[$name])) {
                continue;
            }

            $fields[$this->style->realProperty($name)] = $name;
        }

        return $this->fieldCache[$collectionName] = $fields;
    }

    /**
     * @param class-string $class
     *
     * @return array<string, true>
     */
    private function detectRelationProperties(string $class): array
    {
        if (isset($this->relationCache[$class])) {
            return $this->relationCache[$class];
        }

        $relations = [];

        foreach ($this->reflectProperties($class) as $name => $prop) {
            $type = $prop->getType();
            $types = $type instanceof ReflectionUnionType ? $type->getTypes() : ($type !== null ? [$type] : []);
            foreach ($types as $t) {
                if ($t instanceof ReflectionNamedType && !$t->isBuiltin()) {
                    $relations[$name] = true;
                    break;
                }
            }
        }

        return $this->relationCache[$class] = $relations;
    }

    /**
     * @param class-string $class
     *
     * @return ReflectionClass<object>
     */
    private function reflectClass(string $class): ReflectionClass
    {
        return $this->classCache[$class] ??= new ReflectionClass($class);
    }

    /**
     * @param class-string $class
     *
     * @return array<string, ReflectionProperty>
     */
    private function reflectProperties(string $class): array
    {
        if (!isset($this->propertyCache[$class])) {
            $this->propertyCache[$class] = [];

            foreach ($this->reflectClass($class)->getProperties() as $prop) {
                if ($prop->isStatic()) {
                    continue;
                }

                $this->propertyCache[$class][$prop->name] = $prop;
            }
        }

        return $this->propertyCache[$class];
    }

    private function coerce(ReflectionProperty $prop, mixed $value): mixed
    {
        $type = $prop->getType();

        if ($type === null) {
            throw new DomainException(
                'Property ' . $prop->getDeclaringClass()->getName() . '::$' . $prop->getName()
                . ' must have a type declaration',
            );
        }

        if ($value === null) {
            return $type->allowsNull() ? null : $value;
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->exactMatch($type, $value) ?? $this->coerceToNamedType($type, $value);
        }

        if ($type instanceof ReflectionUnionType) {
            $members = [];
            foreach ($type->getTypes() as $member) {
                if (!($member instanceof ReflectionNamedType)) {
                    continue;
                }

                $members[] = $member;
            }

            // Pass 1: exact type match (no lossy casts)
            foreach ($members as $member) {
                $result = $this->exactMatch($member, $value);
                if ($result !== null) {
                    return $result;
                }
            }

            // Pass 2: lossy coercion (numeric string → int, scalar → string, etc.)
            foreach ($members as $member) {
                $result = $this->coerceToNamedType($member, $value);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /** Accept value only if it already matches the type without any conversion */
    private function exactMatch(ReflectionNamedType $type, mixed $value): mixed
    {
        $name = $type->getName();

        return match (true) {
            $name === 'mixed' => $value,
            $name === 'int' && is_int($value) => $value,
            $name === 'float' && is_float($value) => $value,
            $name === 'string' && is_string($value) => $value,
            $name === 'bool' && is_bool($value) => $value,
            $name === 'array' && is_array($value) => $value,
            is_object($value) && $value instanceof $name => $value,
            default => null,
        };
    }

    /** Accept value with lossy coercion (e.g. numeric string → int) */
    private function coerceToNamedType(ReflectionNamedType $type, mixed $value): mixed
    {
        $name = $type->getName();

        return match (true) {
            $name === 'mixed' => $value,
            $name === 'int' && is_string($value) && is_numeric($value) => (int) $value,
            $name === 'float' && is_int($value) => (float) $value,
            $name === 'float' && is_string($value) && is_numeric($value) => (float) $value,
            $name === 'string' && is_scalar($value) => (string) $value,
            default => null,
        };
    }
}
