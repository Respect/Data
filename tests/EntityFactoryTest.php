<?php

declare(strict_types=1);

namespace Respect\Data;

use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityFactory::class)]
class EntityFactoryTest extends TestCase
{
    #[Test]
    public function createByNameThrowsForUnknownClass(): void
    {
        $factory = new EntityFactory();
        $this->expectException(DomainException::class);
        $factory->createByName('nonexistent_table');
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
    public function setIgnoresUndeclaredProperties(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\Foo();
        $factory->set($entity, 'nonexistent', 42);
        $this->assertNull($factory->get($entity, 'nonexistent'));
    }

    #[Test]
    public function getReturnsNullForMissingProperty(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\Foo();
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
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $source = new Stubs\Author();
        $source->id = 1;
        $source->name = 'test';
        $entity = $factory->hydrate($source, 'author');
        $this->assertEquals(1, $factory->get($entity, 'id'));
        $this->assertEquals('test', $factory->get($entity, 'name'));
    }

    #[Test]
    public function hydrateSkipsUninitializedSourceProperties(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $source = new Stubs\Post();
        $source->id = 1;
        $source->title = 'Test';
        // $source->author is uninitialized — should not be copied
        $entity = $factory->hydrate($source, 'post');
        $this->assertEquals(1, $factory->get($entity, 'id'));
        $this->assertEquals('Test', $factory->get($entity, 'title'));
        $this->assertNull($factory->get($entity, 'author'));
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

    #[Test]
    public function extractColumnsDerivesRelationFk(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $post = new Stubs\Post();
        $post->id = 10;
        $post->title = 'Test';

        $author = new Stubs\Author();
        $author->id = 1;
        $author->name = 'Alice';
        $factory->set($post, 'author', $author);

        $cols = $factory->extractColumns($post);
        $this->assertEquals(1, $cols['author_id']);
        $this->assertArrayNotHasKey('author', $cols);
        $this->assertEquals(10, $cols['id']);
        $this->assertEquals('Test', $cols['title']);
    }

    #[Test]
    public function extractColumnsResolvesFkObjectInPlace(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $parent = new Stubs\Category();
        $parent->id = 3;
        $parent->name = 'Parent';

        $child = new Stubs\Category();
        $child->id = 8;
        $child->name = 'Child';
        $child->category_id = $parent;

        $cols = $factory->extractColumns($child);
        $this->assertEquals(3, $cols['category_id']);
        $this->assertEquals(8, $cols['id']);
        $this->assertEquals('Child', $cols['name']);
    }

    #[Test]
    public function extractColumnsPassesScalarsThrough(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $author = new Stubs\Author();
        $author->id = 5;
        $author->name = 'Bob';

        $cols = $factory->extractColumns($author);
        $this->assertEquals(['id' => 5, 'name' => 'Bob', 'bio' => null], $cols);
    }
}
