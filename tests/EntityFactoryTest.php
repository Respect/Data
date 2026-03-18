<?php

declare(strict_types=1);

namespace Respect\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(EntityFactory::class)]
class EntityFactoryTest extends TestCase
{
    #[Test]
    public function createByNameReturnsStdClassForUnknownClass(): void
    {
        $factory = new EntityFactory();
        $entity = $factory->createByName('nonexistent_table');
        $this->assertInstanceOf(stdClass::class, $entity);
    }

    #[Test]
    public function createByNameReturnsCorrectClassWhenFound(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->createByName('typed_entity');
        $this->assertInstanceOf(Stubs\TypedEntity::class, $entity);
    }

    #[Test]
    public function createByNameWithDisabledConstructorSkipsConstructor(): void
    {
        $factory = new EntityFactory(
            entityNamespace: __NAMESPACE__ . '\\Stubs\\',
            disableConstructor: true,
        );
        $entity = $factory->createByName('typed_entity');
        $this->assertInstanceOf(Stubs\TypedEntity::class, $entity);
        $this->assertNull($entity->value);
    }

    #[Test]
    public function setAndGetWorkOnTypedProperties(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->createByName('typed_entity');
        $factory->set($entity, 'value', 'hello');
        $this->assertEquals('hello', $factory->get($entity, 'value'));
    }

    #[Test]
    public function setAndGetWorkOnDynamicProperties(): void
    {
        $factory = new EntityFactory();
        $entity = new stdClass();
        $factory->set($entity, 'dynamic', 42);
        $this->assertEquals(42, $factory->get($entity, 'dynamic'));
    }

    #[Test]
    public function getReturnsNullForMissingProperty(): void
    {
        $factory = new EntityFactory();
        $entity = new stdClass();
        $this->assertNull($factory->get($entity, 'nonexistent'));
    }

    #[Test]
    public function extractPropertiesReturnsAllProperties(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->createByName('typed_entity');
        $factory->set($entity, 'value', 'test');
        $props = $factory->extractProperties($entity);
        $this->assertArrayHasKey('value', $props);
        $this->assertEquals('test', $props['value']);
    }

    #[Test]
    public function extractPropertiesRespectsNotPersistableAttribute(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->createByName('entity_with_excluded');
        $factory->set($entity, 'name', 'visible');
        $props = $factory->extractProperties($entity);
        $this->assertArrayHasKey('name', $props);
        $this->assertArrayNotHasKey('secret', $props);
    }

    #[Test]
    public function hydrateCreatesEntityWithSourceProperties(): void
    {
        $factory = new EntityFactory();
        $source = new stdClass();
        $source->id = 1;
        $source->name = 'test';
        $entity = $factory->hydrate($source, 'some_table');
        $this->assertEquals(1, $factory->get($entity, 'id'));
        $this->assertEquals('test', $factory->get($entity, 'name'));
    }

    #[Test]
    public function getStyleReturnsConfiguredStyle(): void
    {
        $style = new Styles\CakePHP();
        $factory = new EntityFactory(style: $style);
        $this->assertSame($style, $factory->style);
    }

    #[Test]
    public function extractPropertiesSkipsStaticProperties(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->createByName('edge_case_entity');
        $props = $factory->extractProperties($entity);
        $this->assertArrayNotHasKey('static', $props);
    }

    #[Test]
    public function extractPropertiesSkipsUninitializedProperties(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->createByName('edge_case_entity');
        $props = $factory->extractProperties($entity);
        $this->assertArrayHasKey('initialized', $props);
        $this->assertArrayNotHasKey('uninitialized', $props);
    }

    #[Test]
    public function extractPropertiesIncludesNonPublicProperties(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->createByName('edge_case_entity');
        $props = $factory->extractProperties($entity);
        $this->assertEquals('prot_val', $props['protected']);
        $this->assertEquals('priv_val', $props['private']);
    }

    #[Test]
    public function getReturnsNullForUninitializedTypedProperty(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->createByName('edge_case_entity');
        $this->assertNull($factory->get($entity, 'uninitialized'));
    }
}
