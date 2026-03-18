<?php

declare(strict_types=1);

namespace Respect\Data;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use stdClass;

use function class_exists;
use function get_object_vars;

/** Creates and manipulates entity objects using Style-based naming conventions */
class EntityFactory
{
    /** @var array<string, ReflectionClass<object>> */
    private array $classCache = [];

    /** @var array<string, array<string, ReflectionProperty>> */
    private array $propertyCache = [];

    /** @var array<string, array<string, ReflectionProperty>> */
    private array $persistableCache = [];

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
        $entityClass = class_exists($entityClass) ? $entityClass : stdClass::class;
        $ref = $this->reflectClass($entityClass);

        if (!$this->disableConstructor) {
            return $ref->newInstanceArgs();
        }

        return $ref->newInstanceWithoutConstructor();
    }

    public function set(object $entity, string $prop, mixed $value): void
    {
        $properties = $this->reflectProperties($entity::class);

        if (isset($properties[$prop])) {
            $properties[$prop]->setValue($entity, $value);

            return;
        }

        $entity->{$prop} = $value;
    }

    public function get(object $entity, string $prop): mixed
    {
        $mirror = $this->reflectProperties($entity::class)[$prop] ?? null;

        if ($mirror !== null) {
            return $mirror->isInitialized($entity) ? $mirror->getValue($entity) : null;
        }

        try {
            return (new ReflectionProperty($entity, $prop))->getValue($entity);
        } catch (ReflectionException) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    public function extractProperties(object $entity): array
    {
        $props = get_object_vars($entity);
        $persistable = $this->reflectPersistable($entity::class);

        foreach ($this->reflectProperties($entity::class) as $name => $prop) {
            if (!isset($persistable[$name]) || !$prop->isInitialized($entity)) {
                unset($props[$name]);

                continue;
            }

            $props[$name] = $prop->getValue($entity);
        }

        return $props;
    }

    public function hydrate(object $source, string $entityName): object
    {
        $entity = $this->createByName($entityName);

        foreach (get_object_vars($source) as $prop => $value) {
            $this->set($entity, $prop, $value);
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

    /** @return array<string, ReflectionProperty> */
    private function reflectPersistable(string $class): array
    {
        if (!isset($this->persistableCache[$class])) {
            $this->persistableCache[$class] = [];

            foreach ($this->reflectProperties($class) as $name => $prop) {
                if ($prop->getAttributes(NotPersistable::class)) {
                    continue;
                }

                $this->persistableCache[$class][$name] = $prop;
            }
        }

        return $this->persistableCache[$class];
    }
}
