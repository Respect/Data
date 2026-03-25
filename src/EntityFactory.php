<?php

declare(strict_types=1);

namespace Respect\Data;

use DomainException;
use ReflectionClass;
use ReflectionProperty;

use function class_exists;
use function is_object;

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
        $mirror = $this->reflectProperties($entity::class)[$prop] ?? null;

        $mirror?->setValue($entity, $value);
    }

    public function get(object $entity, string $prop): mixed
    {
        $mirror = $this->reflectProperties($entity::class)[$prop] ?? null;

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

        foreach ($cols as $key => $value) {
            if (!is_object($value)) {
                continue;
            }

            if ($this->style->isRelationProperty($key)) {
                $fk = $this->style->remoteIdentifier($key);
                $cols[$fk] = $this->get($value, $this->style->identifier($key));
                unset($cols[$key]);
            } else {
                $table = $this->style->remoteFromIdentifier($key) ?? $key;
                $cols[$key] = $this->get($value, $this->style->identifier($table));
            }
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
}
