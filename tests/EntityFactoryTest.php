<?php

declare(strict_types=1);

namespace Respect\Data;

use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

use function assert;

#[CoversClass(EntityFactory::class)]
#[CoversClass(ReadOnlyViolation::class)]
class EntityFactoryTest extends TestCase
{
    #[Test]
    public function resolveClassThrowsForUnknownClass(): void
    {
        $factory = new EntityFactory();
        $this->expectException(DomainException::class);
        $factory->resolveClass('nonexistent_table');
    }

    #[Test]
    public function resolveClassReturnsCorrectClassWhenFound(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $class = $factory->resolveClass('typed_entity');
        $this->assertSame(Stubs\TypedEntity::class, $class);
    }

    #[Test]
    public function createWithResolvedClassSkipsConstructor(): void
    {
        $factory = new EntityFactory(
            entityNamespace: __NAMESPACE__ . '\\Stubs\\',
        );
        $entity = $factory->create($factory->resolveClass('typed_entity'));
        $this->assertInstanceOf(Stubs\TypedEntity::class, $entity);
        $this->assertNull($entity->value);
    }

    #[Test]
    public function setAndGetWorkOnTypedProperties(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->create($factory->resolveClass('typed_entity'));
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
        $entity = $factory->create($factory->resolveClass('typed_entity'));
        $factory->set($entity, 'value', 'test');
        $props = $factory->extractProperties($entity);
        $this->assertArrayHasKey('value', $props);
        $this->assertEquals('test', $props['value']);
    }

    #[Test]
    public function extractPropertiesRespectsNotPersistableAttribute(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->create($factory->resolveClass('entity_with_excluded'));
        $factory->set($entity, 'name', 'visible');
        $props = $factory->extractProperties($entity);
        $this->assertArrayHasKey('name', $props);
        $this->assertArrayNotHasKey('secret', $props);
    }

    #[Test]
    public function createAndCopyPropertiesReproducesEntity(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $source = new Stubs\Author();
        $source->id = 1;
        $source->name = 'test';
        $entity = $factory->create($factory->resolveClass('author'));
        foreach ($factory->extractProperties($source) as $name => $value) {
            $factory->set($entity, $name, $value);
        }

        $this->assertEquals(1, $factory->get($entity, 'id'));
        $this->assertEquals('test', $factory->get($entity, 'name'));
    }

    #[Test]
    public function createAndCopySkipsUninitializedSourceProperties(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $source = new Stubs\Post();
        $source->id = 1;
        $source->title = 'Test';
        // $source->author is uninitialized — extractProperties skips it
        $entity = $factory->create($factory->resolveClass('post'));
        foreach ($factory->extractProperties($source) as $name => $value) {
            $factory->set($entity, $name, $value);
        }

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
        $entity = $factory->create($factory->resolveClass('edge_case_entity'));
        $props = $factory->extractProperties($entity);
        $this->assertArrayNotHasKey('static', $props);
    }

    #[Test]
    public function extractPropertiesSkipsUninitializedProperties(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->create($factory->resolveClass('edge_case_entity'));
        $props = $factory->extractProperties($entity);
        $this->assertArrayHasKey('initialized', $props);
        $this->assertArrayNotHasKey('uninitialized', $props);
    }

    #[Test]
    public function extractPropertiesIncludesNonPublicProperties(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->create($factory->resolveClass('edge_case_entity'));
        $props = $factory->extractProperties($entity);
        $this->assertEquals('prot_val', $props['protected']);
        $this->assertEquals('priv_val', $props['private']);
    }

    #[Test]
    public function getReturnsNullForUninitializedTypedProperty(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->create($factory->resolveClass('edge_case_entity'));
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
        $child->category = $parent;

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

    #[Test]
    public function extractColumnsExcludesUninitializedRelation(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $post = new Stubs\Post();
        $post->id = 10;
        $post->title = 'Test';

        $cols = $factory->extractColumns($post);
        $this->assertArrayNotHasKey('author', $cols);
        $this->assertArrayNotHasKey('author_id', $cols);
        $this->assertEquals(10, $cols['id']);
        $this->assertEquals('Test', $cols['title']);
    }

    #[Test]
    public function setSkipsIncompatibleType(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\TypeCoercionEntity();
        $entity->id = 1;

        // Non-coercible value leaves the non-nullable property uninitialized
        $factory->set($entity, 'strict', 'not-a-number');
        $ref = new ReflectionProperty($entity, 'strict');
        $this->assertFalse($ref->isInitialized($entity));
    }

    #[Test]
    public function setCoercesNumericStringToInt(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\TypeCoercionEntity();

        $factory->set($entity, 'id', '42');
        $this->assertSame(42, $entity->id);
    }

    #[Test]
    public function setHandlesUnionType(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\TypeCoercionEntity();

        // Union type int|string|null — exact match takes priority over lossy coercion
        $factory->set($entity, 'flexible', '99');
        $this->assertSame('99', $entity->flexible);

        // Int stays int (exact match on int branch, not lossy-cast to string)
        $factory->set($entity, 'flexible', 42);
        $this->assertSame(42, $entity->flexible);

        // Null should work (nullable union)
        $factory->set($entity, 'flexible', null);
        $this->assertNull($entity->flexible);
    }

    #[Test]
    public function coercionFailureFallsThrough(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\TypeCoercionEntity();
        $entity->id = 1;

        // Setting an object on an int|string|null union fails all branches —
        // property stays unchanged since the union includes null (nullable)
        $entity->flexible = 'original';
        $factory->set($entity, 'flexible', new stdClass());
        $this->assertNull($entity->flexible);
    }

    #[Test]
    public function unionLossyCoercionKicksInWhenExactMatchFails(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\TypeCoercionEntity();
        $entity->id = 1;

        // int|float with a numeric string — exact match fails (not int, not float),
        // lossy pass coerces '42' → 42 (int branch wins)
        $factory->set($entity, 'narrow_union', '42');
        $this->assertSame(42, $entity->narrowUnion);
    }

    #[Test]
    public function isReadOnlyDetectsReadOnlyClass(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $this->assertTrue($factory->isReadOnly($factory->create(Stubs\ReadOnlyAuthor::class, name: 'test')));
        $this->assertFalse($factory->isReadOnly(new Stubs\Author()));
    }

    #[Test]
    public function resolveClassAutoDetectsReadOnly(): void
    {
        $factory = new EntityFactory(
            entityNamespace: __NAMESPACE__ . '\\Stubs\\',
        );
        $class = $factory->resolveClass('read_only_author');
        $this->assertSame(Stubs\ReadOnlyAuthor::class, $class);
        $entity = $factory->create($class);
        $this->assertInstanceOf(Stubs\ReadOnlyAuthor::class, $entity);
        $ref = new ReflectionProperty($entity, 'name');
        $this->assertFalse($ref->isInitialized($entity));
    }

    #[Test]
    public function setOnUninitializedReadOnlyPropertySucceeds(): void
    {
        $factory = new EntityFactory(
            entityNamespace: __NAMESPACE__ . '\\Stubs\\',
        );
        $entity = $factory->create($factory->resolveClass('read_only_author'));
        assert($entity instanceof Stubs\ReadOnlyAuthor);
        $factory->set($entity, 'id', 42);
        $factory->set($entity, 'name', 'Alice');
        $this->assertSame(42, $entity->id);
        $this->assertSame('Alice', $entity->name);
    }

    #[Test]
    public function setOnInitializedReadOnlyPropertyThrowsReadOnlyViolation(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\ReadOnlyAuthor(id: 1, name: 'Alice');

        $this->expectException(ReadOnlyViolation::class);
        $this->expectExceptionMessage('Cannot modify readonly property');
        $factory->set($entity, 'name', 'Bob');
    }

    #[Test]
    public function extractPropertiesWorksOnReadOnlyEntity(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\ReadOnlyAuthor(id: 5, name: 'Alice', bio: 'bio text');

        $props = $factory->extractProperties($entity);
        $this->assertEquals(['id' => 5, 'name' => 'Alice', 'bio' => 'bio text'], $props);
    }

    #[Test]
    public function extractColumnsResolvesReadOnlyRelationFk(): void
    {
        $factory = new EntityFactory(
            entityNamespace: __NAMESPACE__ . '\\Stubs\\Immutable\\',
        );

        $author = new Stubs\Immutable\Author(id: 3, name: 'Alice');

        $post = $factory->create($factory->resolveClass('post'));
        $factory->set($post, 'id', 10);
        $factory->set($post, 'title', 'Test');
        $factory->set($post, 'author', $author);

        $cols = $factory->extractColumns($post);
        $this->assertEquals(3, $cols['author_id']);
        $this->assertArrayNotHasKey('author', $cols);
        $this->assertEquals(10, $cols['id']);
        $this->assertEquals('Test', $cols['title']);
    }

    #[Test]
    public function withChangesCreatesModifiedCopy(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\ReadOnlyAuthor(id: 1, name: 'Alice', bio: 'bio');

        $copy = $factory->withChanges($entity, name: 'Bob');
        assert($copy instanceof Stubs\ReadOnlyAuthor);

        $this->assertSame(1, $copy->id);
        $this->assertSame('Bob', $copy->name);
        $this->assertSame('bio', $copy->bio);
        $this->assertSame('Alice', $entity->name);
    }

    #[Test]
    public function withChangesPreservesPkForIdentityMapLookup(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\Immutable\\');

        $author = new Stubs\Immutable\Author(id: 5, name: 'Alice');

        $post = new Stubs\Immutable\Post(id: 10, title: 'Hello', text: 'World', author: $author);

        $bob = new Stubs\Immutable\Author(id: 6, name: 'Bob');

        $copy = $factory->withChanges($post, title: 'Changed', author: $bob);
        assert($copy instanceof Stubs\Immutable\Post);

        $this->assertSame(10, $copy->id);
        $this->assertSame('Changed', $copy->title);
        $this->assertSame('World', $copy->text);
        $this->assertInstanceOf(Stubs\Immutable\Author::class, $copy->author);
        $this->assertSame('Bob', $copy->author->name);
        $this->assertSame(6, $copy->author->id);
    }

    #[Test]
    public function withChangesWorksOnMutableEntities(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $author = new Stubs\Author();
        $author->id = 1;
        $author->name = 'Alice';
        $author->bio = 'bio';

        $copy = $factory->withChanges($author, name: 'Bob');
        assert($copy instanceof Stubs\Author);
        $this->assertSame(1, $copy->id);
        $this->assertSame('Bob', $copy->name);
        $this->assertSame('bio', $copy->bio);
    }

    #[Test]
    public function withChangesThrowsOnUnknownProperty(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\ReadOnlyAuthor(id: 1, name: 'Alice');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown properties');
        $factory->withChanges($entity, nname: 'Bob');
    }

    #[Test]
    public function withChangesAppliesNullValue(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\ReadOnlyAuthor(id: 1, name: 'Alice', bio: 'has bio');

        $copy = $factory->withChanges($entity, bio: null);
        assert($copy instanceof Stubs\ReadOnlyAuthor);
        $this->assertNull($copy->bio);
        $this->assertSame('Alice', $copy->name);
        $this->assertSame(1, $copy->id);
    }

    #[Test]
    public function withChangesPreservesUninitializedProperties(): void
    {
        $factory = new EntityFactory(
            entityNamespace: __NAMESPACE__ . '\\Stubs\\',
        );

        $entity = $factory->create($factory->resolveClass('read_only_author'));
        $factory->set($entity, 'name', 'Alice');
        // $id and $bio are uninitialized

        $copy = $factory->withChanges($entity, name: 'Bob');
        assert($copy instanceof Stubs\ReadOnlyAuthor);
        $this->assertSame('Bob', $copy->name);
        $this->assertFalse((new ReflectionProperty($copy, 'id'))->isInitialized($copy));
        $this->assertFalse((new ReflectionProperty($copy, 'bio'))->isInitialized($copy));
    }

    #[Test]
    public function withChangesWithEmptyChangesReturnsCopy(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\ReadOnlyAuthor(id: 1, name: 'Alice', bio: 'bio');

        $copy = $factory->withChanges($entity);
        assert($copy instanceof Stubs\ReadOnlyAuthor);
        $this->assertNotSame($entity, $copy);
        $this->assertSame(1, $copy->id);
        $this->assertSame('Alice', $copy->name);
        $this->assertSame('bio', $copy->bio);
    }

    #[Test]
    public function createAndCopyWorksOnReadOnlyEntity(): void
    {
        $factory = new EntityFactory(
            entityNamespace: __NAMESPACE__ . '\\Stubs\\',
        );

        $source = $factory->create($factory->resolveClass('read_only_author'));
        assert($source instanceof Stubs\ReadOnlyAuthor);
        $factory->set($source, 'id', 1);
        $factory->set($source, 'name', 'Source');

        $entity = $factory->create($factory->resolveClass('read_only_author'));
        foreach ($factory->extractProperties($source) as $name => $value) {
            $factory->set($entity, $name, $value);
        }

        assert($entity instanceof Stubs\ReadOnlyAuthor);
        $this->assertSame(1, $entity->id);
        $this->assertSame('Source', $entity->name);
    }

    #[Test]
    public function withChangesCoercesTypes(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\ReadOnlyAuthor(id: 1, name: 'Alice');

        $copy = $factory->withChanges($entity, name: 42);
        assert($copy instanceof Stubs\ReadOnlyAuthor);
        $this->assertSame('42', $copy->name);
    }

    #[Test]
    public function withChangesThrowsOnInvalidValue(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = new Stubs\ReadOnlyAuthor(id: 1, name: 'Alice');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Invalid value');
        $factory->withChanges($entity, name: null);
    }

    #[Test]
    public function mergeEntitiesReturnsMergedCloneWhenPropertiesDiffer(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\Immutable\\');

        $base = new Stubs\Immutable\Author(id: 1, name: 'Alice', bio: 'Original bio');
        $overlay = $factory->create(Stubs\Immutable\Author::class, name: 'Bob');

        $merged = $factory->mergeEntities($base, $overlay);
        assert($merged instanceof Stubs\Immutable\Author);

        $this->assertNotSame($base, $merged);
        $this->assertNotSame($overlay, $merged);
        $this->assertSame(1, $merged->id);
        $this->assertSame('Bob', $merged->name);
        $this->assertSame('Original bio', $merged->bio);
    }

    #[Test]
    public function mergeEntitiesReturnsBaseWhenNoDifference(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\Immutable\\');

        $base = new Stubs\Immutable\Author(id: 1, name: 'Alice');
        $overlay = $factory->create(Stubs\Immutable\Author::class, id: 1, name: 'Alice');

        $merged = $factory->mergeEntities($base, $overlay);

        $this->assertSame($base, $merged);
    }

    #[Test]
    public function mergeEntitiesThrowsOnClassMismatch(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\Immutable\\');

        $base = new Stubs\Immutable\Author(id: 1, name: 'Alice');
        $overlay = new Stubs\Immutable\Post(id: 1, title: 'Title');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot merge entities of different classes');
        $factory->mergeEntities($base, $overlay);
    }

    #[Test]
    public function mergeEntitiesClonesWhenBasePropertyUninitialized(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\Immutable\\');

        $base = $factory->create(Stubs\Immutable\Author::class, id: 1);
        $overlay = $factory->create(Stubs\Immutable\Author::class, name: 'Bob');

        $merged = $factory->mergeEntities($base, $overlay);
        assert($merged instanceof Stubs\Immutable\Author);

        $this->assertNotSame($base, $merged);
        $this->assertSame(1, $merged->id);
        $this->assertSame('Bob', $merged->name);
    }

    #[Test]
    public function enumerateFieldsReturnsScalarColumnsOnly(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $fields = $factory->enumerateFields('post');

        $this->assertSame(['id' => 'id', 'title' => 'title', 'text' => 'text'], $fields);
    }

    #[Test]
    public function enumerateFieldsExcludesNotPersistable(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $fields = $factory->enumerateFields('entity_with_excluded');

        $this->assertSame(['name' => 'name'], $fields);
    }

    #[Test]
    public function enumerateFieldsCachesResults(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $first = $factory->enumerateFields('author');
        $second = $factory->enumerateFields('author');

        $this->assertSame($first, $second);
    }

    #[Test]
    public function setWithStyledFlagSkipsConversion(): void
    {
        $factory = new EntityFactory(entityNamespace: __NAMESPACE__ . '\\Stubs\\');
        $entity = $factory->create($factory->resolveClass('author'));

        $factory->set($entity, 'name', 'Alice', styled: true);
        $this->assertSame('Alice', $factory->get($entity, 'name'));
    }
}
