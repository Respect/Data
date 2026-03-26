<?php

declare(strict_types=1);

namespace Respect\Data;

use DomainException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

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
    /** @var array<string, ReflectionClass<object>> */
    private array $classCache = [];

    /** @var array<string, array<string, ReflectionProperty>> */
    private array $propertyCache = [];

    public function __construct(
        public readonly Styles\Stylable $style = new Styles\Standard(),
        private readonly string $entityNamespace = '\\',
        private readonly bool $disableConstructor = false,
    ) {
    }

    public function createByName(string $name): object
    {
        $entityName = $this->style->styledName($name);
        $entityClass = $this->entityNamespace . $entityName;

        if (!class_exists($entityClass)) {
            throw new DomainException('Entity class ' . $entityClass . ' not found for ' . $name);
        }

        $ref = $this->reflectClass($entityClass);

        if (!$this->disableConstructor) {
            return $ref->newInstanceArgs();
        }

        return $ref->newInstanceWithoutConstructor();
    }

    public function set(object $entity, string $prop, mixed $value): void
    {
        $styledProp = $this->style->styledProperty($prop);
        $mirror = $this->reflectProperties($entity::class)[$styledProp] ?? null;

        if ($mirror === null) {
            return;
        }

        $coerced = $this->coerce($mirror, $value);

        if ($coerced === null && !($mirror->getType()?->allowsNull() ?? false)) {
            return;
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

    /**
     * Extract persistable columns, resolving entity objects to their FK representations.
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

            $fk = $this->style->remoteIdentifier($key);

            if (is_object($value)) {
                $cols[$fk] = $this->get($value, $this->style->identifier($key));
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
            if (!$prop->isInitialized($entity) || $prop->getAttributes(NotPersistable::class)) {
                continue;
            }

            $props[$name] = $prop->getValue($entity);
        }

        return $props;
    }

    public function hydrate(object $source, string $entityName): object
    {
        $entity = $this->createByName($entityName);

        foreach ($this->reflectProperties($source::class) as $name => $prop) {
            if (!$prop->isInitialized($source)) {
                continue;
            }

            $this->set($entity, $name, $prop->getValue($source));
        }

        return $entity;
    }

    /** @return array<string, true> */
    private function detectRelationProperties(string $class): array
    {
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

        return $relations;
    }

    /** @return ReflectionClass<object> */
    private function reflectClass(string $class): ReflectionClass
    {
        return $this->classCache[$class] ??= new ReflectionClass($class); // @phpstan-ignore argument.type
    }

    /** @return array<string, ReflectionProperty> */
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
