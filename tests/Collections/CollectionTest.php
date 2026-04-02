<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrators\Nested;
use Respect\Data\InMemoryMapper;
use Respect\Data\Stubs;

use function count;

#[CoversClass(Collection::class)]
class CollectionTest extends TestCase
{
    #[Test]
    public function collectionCanBeCreatedStaticallyWithJustName(): void
    {
        $coll = Collection::fooBarName();
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
    }

    #[Test]
    public function collectionCanBeCreatedStaticallyWithChildren(): void
    {
        $children1 = Collection::bar();
        $children2 = Collection::baz();
        $coll = Collection::foo([$children1, $children2]);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertTrue($coll->hasChildren);
        $this->assertEquals(2, count($coll->with));
    }

    #[Test]
    public function collectionCanBeCreatedStaticallyWithFilter(): void
    {
        $coll = Collection::fooBar(filter: 42);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertEquals(42, $coll->filter);
    }

    #[Test]
    public function objectConstructorShouldSetObjectAttributes(): void
    {
        $coll = new Collection('some_irrelevant_name');
        $this->assertNull(
            $coll->filter,
            'Default filter should be null',
        );
        $this->assertEquals('some_irrelevant_name', $coll->name);
    }

    #[Test]
    public function objectConstructorWithFilterShouldSetIt(): void
    {
        $coll = new Collection('some_irrelevant_name', filter: 123);
        $this->assertEquals(123, $coll->filter);
    }

    #[Test]
    public function constructorCompositionShouldSetChildrenAndParent(): void
    {
        $child = new Collection('bar_child');
        $coll = new Collection('foo_collection', [$child]);
        $children = $coll->with;
        $this->assertCount(1, $children);
        $this->assertInstanceOf(Collection::class, $children[0]);
        $this->assertEquals(false, $children[0]->required);
        $this->assertEquals($coll->name, $children[0]->parent?->name);
    }

    #[Test]
    public function childrenShouldMakeHasChildrenTrue(): void
    {
        $coll = Collection::foo([Collection::thisIsAChildren()]);
        $this->assertTrue($coll->hasChildren);
    }

    #[Test]
    public function noChildrenShouldMakeHasChildrenFalse(): void
    {
        $coll = Collection::foo();
        $this->assertFalse($coll->hasChildren);
    }

    #[Test]
    public function getParentShouldReturnNullWhenNoParent(): void
    {
        $coll = new Collection('foo');
        $this->assertNull($coll->parent);
    }

    #[Test]
    public function deriveCreatesNewCollectionWithMergedWith(): void
    {
        $original = Collection::foo([Collection::bar()]);
        $derived = $original->derive(with: [Collection::baz()]);

        $this->assertNotSame($original, $derived);
        $this->assertEquals('foo', $derived->name);
        $this->assertCount(2, $derived->with);
        $this->assertEquals('bar', $derived->with[0]->name);
        $this->assertEquals('baz', $derived->with[1]->name);
        $this->assertCount(1, $original->with, 'Original should be unchanged');
    }

    #[Test]
    public function derivePreservesFilterAndOverridesWhenProvided(): void
    {
        $original = new Collection('foo', filter: 42, required: true);

        $sameFilter = $original->derive();
        $this->assertEquals(42, $sameFilter->filter);
        $this->assertTrue($sameFilter->required);

        $newFilter = $original->derive(filter: 99, required: false);
        $this->assertEquals(99, $newFilter->filter);
        $this->assertFalse($newFilter->required);
    }

    #[Test]
    public function cloneDeepClonesChildrenAndClearsParent(): void
    {
        $parent = Collection::foo([Collection::bar([Collection::baz()])]);
        $clone = clone $parent;

        $this->assertNull($clone->parent);
        $this->assertNotSame($parent->with[0], $clone->with[0]);
        $this->assertEquals('bar', $clone->with[0]->name);
        $this->assertSame($clone, $clone->with[0]->parent);

        $this->assertNotSame($parent->with[0]->with[0], $clone->with[0]->with[0]);
        $this->assertEquals('baz', $clone->with[0]->with[0]->name);
    }

    #[Test]
    public function persistReturnsSameEntity(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('author', []);

        $entity = $mapper->entityFactory->create(Stubs\Immutable\Author::class, name: 'Alice');
        $result = $mapper->persist($entity, $mapper->author());
        $this->assertSame($entity, $result);
    }

    #[Test]
    public function persistPartialEntityMergesViaIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->fetch($mapper->author(filter: 1));
        $this->assertSame('Alice', $fetched->name);

        $partial = $mapper->entityFactory->create(Stubs\Immutable\Author::class, id: 1, name: 'Bob');
        $result = $mapper->persist($partial, $mapper->author());

        $this->assertNotSame($fetched, $result);
        $this->assertSame('Bob', $result->name);
        $this->assertSame(1, $result->id);
    }

    #[Test]
    public function persistPartialEntityFlushesUpdate(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $mapper->fetch($mapper->author(filter: 1));

        $partial = $mapper->entityFactory->create(Stubs\Immutable\Author::class, id: 1, name: 'Bob', bio: 'Writer');
        $mapper->persist($partial, $mapper->author());
        $mapper->flush();

        $mapper->clearIdentityMap();
        $refetched = $mapper->fetch($mapper->author(filter: 1));
        $this->assertSame('Bob', $refetched->name);
        $this->assertSame('Writer', $refetched->bio);
    }

    #[Test]
    public function persistPartialEntityOnGraphUpdatesRelation(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body', 'author_id' => 10],
        ]);
        $mapper->seed('author', [
            ['id' => 10, 'name' => 'Alice', 'bio' => null],
            ['id' => 20, 'name' => 'Bob', 'bio' => null],
        ]);

        $mapper->fetch($mapper->post([$mapper->author()]));
        $bob = $mapper->fetch($mapper->author(filter: 20));

        $updated = $mapper->entityFactory->create(Stubs\Immutable\Post::class, id: 1, title: 'Changed', author: $bob);
        $result = $mapper->persist($updated, $mapper->post());
        $mapper->flush();

        $this->assertSame(1, $result->id);

        $mapper->clearIdentityMap();
        $refetched = $mapper->fetch($mapper->post([$mapper->author()]));
        $this->assertSame('Changed', $refetched->title);
        $this->assertSame('Bob', $refetched->author->name);
    }

    #[Test]
    public function persistPartialEntityNullValueApplied(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => 'has bio'],
        ]);

        $fetched = $mapper->fetch($mapper->author(filter: 1));
        $this->assertSame('has bio', $fetched->bio);

        $partial = $mapper->entityFactory->create(Stubs\Immutable\Author::class, id: 1, name: 'Alice', bio: null);
        $mapper->persist($partial, $mapper->author());
        $mapper->flush();

        $mapper->clearIdentityMap();
        $refetched = $mapper->fetch($mapper->author(filter: 1));
        $this->assertNull($refetched->bio);
    }
}
