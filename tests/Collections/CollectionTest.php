<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\AbstractMapper;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrators\Nested;
use Respect\Data\InMemoryMapper;
use Respect\Data\Stubs;
use Respect\Data\Stubs\Foo;
use RuntimeException;

use function count;
use function reset;

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
        $coll = Collection::foo($children1, $children2);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertTrue($coll->hasChildren);
        $this->assertEquals(2, count($coll->children));
    }

    #[Test]
    public function collectionCanBeCreatedStaticallyWithCondition(): void
    {
        $coll = Collection::fooBar(42);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertEquals(42, $coll->condition);
    }

    #[Test]
    public function multipleConditionsOnStaticCreationLeavesTheLast(): void
    {
        $coll = Collection::fooBar(42, 'Other dominant condition!!!');
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertEquals(
            'Other dominant condition!!!',
            $coll->condition,
        );
    }

    #[Test]
    public function objectConstructorShouldSetObjectAttributes(): void
    {
        $coll = new Collection('some_irrelevant_name');
        $this->assertEquals(
            [],
            $coll->condition,
            'Default condition should be an empty array',
        );
        $this->assertEquals('some_irrelevant_name', $coll->name);
    }

    #[Test]
    public function objectConstructorWithConditionShouldSetIt(): void
    {
        $coll = new Collection('some_irrelevant_name', 123);
        $this->assertEquals(123, $coll->condition);
    }

    #[Test]
    public function dynamicGetterShouldStackCollection(): void
    {
        $coll = new Collection('hi');
        $coll->someTest;
        $this->assertEquals(
            'someTest',
            $coll->next?->name,
            'First time should change next item',
        );
    }

    #[Test]
    public function dynamicGetterShouldChainCollection(): void
    {
        $coll = new Collection('hi');
        $coll->someTest;
        $this->assertEquals(
            'someTest',
            $coll->next?->name,
            'First time should change next item',
        );
        $coll->anotherTest;
        $this->assertEquals(
            'someTest',
            $coll->next?->name,
            'The next item on a chain should never be changed after first time',
        );
    }

    #[Test]
    public function settingConditionViaDynamicOffsetShouldUseLastNode(): void
    {
        $foo = Collection::foo()->bar->baz[42];
        $bar = $foo->next;
        $baz = $bar->next;
        $this->assertEmpty($foo->condition);
        $this->assertEmpty($bar->condition);
        $this->assertEquals(42, $baz->condition);
    }

    #[Test]
    public function dynamicMethodCallShouldAcceptChildren(): void
    {
        $coll = new Collection('some_name');
        $coll->fooBar(
            Collection::some(),
            Collection::children(),
            Collection::here(),
        );
        $next = $coll->next;
        $this->assertNotNull($next);
        $this->assertEquals(3, count($next->children));
    }

    #[Test]
    public function addChildShouldSetChildrenObjectProperties(): void
    {
        $coll = new Collection('foo_collection');
        $coll->addChild(new Collection('bar_child'));
        $children = $coll->children;
        $child = reset($children);
        $this->assertInstanceOf(Collection::class, $child);
        $this->assertEquals(false, $child->required);
        $this->assertEquals($coll->name, $child->parent?->name);
    }

    #[Test]
    public function childrenShouldMakeHasMoreTrue(): void
    {
        $coll = Collection::foo(Collection::thisIsAChildren());
        $this->assertTrue($coll->more);
    }

    #[Test]
    public function chainingShouldMakeHasMoreTrue(): void
    {
        $coll = Collection::foo()->barChain;
        $this->assertTrue($coll->more);
    }

    #[Test]
    public function arrayOffsetSetShouldNotDoAnything(): void
    {
        $touched = Collection::foo()->bar;
        $touched['magic'] = 'FOOOO';
        $untouched = Collection::foo()->bar;
        $this->assertEquals($untouched, $touched);
    }

    #[Test]
    public function arrayOffsetUnsetShouldNotDoAnything(): void
    {
        $touched = Collection::foo()->bar;
        unset($touched['magic']);
        unset($touched['bar']);
        $untouched = Collection::foo()->bar;
        $this->assertEquals($untouched, $touched);
    }

    #[Test]
    public function persistShouldPersistOnAttachedMapper(): void
    {
        $persisted = new Foo();
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
            ->method('persist')
            ->with($persisted, $collection)
            ->willReturn($persisted);
        $collection->mapper = $mapperMock;
        $result = $collection->persist($persisted);
        $this->assertSame($persisted, $result);
    }

    #[Test]
    public function removeShouldPersistOnAttachedMapper(): void
    {
        $removed = new Foo();
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
            ->method('remove')
            ->with($removed, $collection)
            ->willReturn(true);
        $collection->mapper = $mapperMock;
        $collection->remove($removed);
    }

    #[Test]
    public function fetchShouldPersistOnAttachedMapper(): void
    {
        $result = 'result stub';
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
            ->method('fetch')
            ->with($collection)
            ->willReturn($result);
        $collection->mapper = $mapperMock;
        $collection->fetch();
    }

    #[Test]
    public function fetchShouldPersistOnAttachedMapperWithExtraParam(): void
    {
        $result = 'result stub';
        $extra = 'extra stub';
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
            ->method('fetch')
            ->with($collection, $extra)
            ->willReturn($result);
        $collection->mapper = $mapperMock;
        $collection->fetch($extra);
    }

    #[Test]
    public function fetchAllShouldPersistOnAttachedMapper(): void
    {
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
            ->method('fetchAll')
            ->with($collection)
            ->willReturn([]);
        $collection->mapper = $mapperMock;
        $collection->fetchAll();
    }

    #[Test]
    public function fetchAllShouldPersistOnAttachedMapperWithExtraParam(): void
    {
        $extra = 'extra stub';
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
            ->method('fetchAll')
            ->with($collection, $extra)
            ->willReturn([]);
        $collection->mapper = $mapperMock;
        $collection->fetchAll($extra);
    }

    #[Test]
    public function arrayOffsetExistsShouldNotDoAnything(): void
    {
        $touched = Collection::foo()->bar;
        $this->assertFalse(isset($touched['magic']));
        $untouched = Collection::foo()->bar;
        $this->assertEquals($untouched, $touched);
    }

    #[Test]
    public function persistOnCollectionShouldExceptionIfMapperDontExist(): void
    {
        $this->expectException(RuntimeException::class);
        Collection::foo()->persist(new Foo());
    }

    #[Test]
    public function removeOnCollectionShouldExceptionIfMapperDontExist(): void
    {
        $this->expectException(RuntimeException::class);
        Collection::foo()->remove(new Foo());
    }

    #[Test]
    public function fetchOnCollectionShouldExceptionIfMapperDontExist(): void
    {
        $this->expectException(RuntimeException::class);
        Collection::foo()->fetch();
    }

    #[Test]
    public function fetchAllOnCollectionShouldExceptionIfMapperDontExist(): void
    {
        $this->expectException(RuntimeException::class);
        Collection::foo()->fetchAll();
    }

    #[Test]
    public function getParentShouldReturnNullWhenNoParent(): void
    {
        $coll = new Collection('foo');
        $this->assertNull($coll->parent);
    }

    #[Test]
    public function getNextShouldReturnNullWhenNoNext(): void
    {
        $coll = new Collection('foo');
        $this->assertNull($coll->next);
    }

    #[Test]
    public function magicGetShouldUseRegisteredCollectionFromMapper(): void
    {
        $registered = Collection::bar();
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->method('__isset')->with('bar')->willReturn(true);
        $mapperMock->method('__get')->with('bar')->willReturn($registered);

        $coll = new Collection('foo');
        $coll->mapper = $mapperMock;
        $result = $coll->bar;
        $this->assertEquals('bar', $result->next?->name);
    }

    #[Test]
    public function hydrateFromSetsHydrator(): void
    {
        $hydrator = new Nested();
        $coll = Collection::foo()->hydrateFrom($hydrator);
        $this->assertSame($hydrator, $coll->hydrator);
    }

    #[Test]
    public function persistWithoutChangesReturnsSameEntity(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('author', []);

        $entity = $mapper->entityFactory->create(Stubs\Immutable\Author::class, name: 'Alice');
        $result = $mapper->author->persist($entity);
        $this->assertSame($entity, $result);
    }

    #[Test]
    public function persistWithChangesReturnsModifiedCopy(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->author[1]->fetch();

        $result = $mapper->author[1]->persist($fetched, name: 'Bob');

        $this->assertNotSame($fetched, $result);
        $this->assertSame('Bob', $result->name);
        $this->assertSame(1, $result->id);
        $this->assertSame('Alice', $fetched->name);
    }

    #[Test]
    public function persistWithChangesFlushesUpdate(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->author[1]->fetch();
        $mapper->author[1]->persist($fetched, name: 'Bob', bio: 'Writer');
        $mapper->flush();

        $mapper->clearIdentityMap();
        $refetched = $mapper->author[1]->fetch();
        $this->assertSame('Bob', $refetched->name);
        $this->assertSame('Writer', $refetched->bio);
    }

    #[Test]
    public function persistWithChangesOnGraphUpdatesRelation(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body', 'author_id' => 10],
        ]);
        $mapper->seed('author', [
            ['id' => 10, 'name' => 'Alice', 'bio' => null],
            ['id' => 20, 'name' => 'Bob', 'bio' => null],
        ]);

        $post = $mapper->post->author->fetch();
        $bob = $mapper->author[20]->fetch();

        $updated = $mapper->post->persist($post, title: 'Changed', author: $bob);
        $mapper->flush();

        $this->assertSame(1, $updated->id);

        $mapper->clearIdentityMap();
        $refetched = $mapper->post->author->fetch();
        $this->assertSame('Changed', $refetched->title);
        $this->assertSame('Bob', $refetched->author->name);
    }

    #[Test]
    public function persistWithChangesNullValueApplied(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => 'has bio'],
        ]);

        $fetched = $mapper->author[1]->fetch();
        $this->assertSame('has bio', $fetched->bio);

        $mapper->author[1]->persist($fetched, bio: null);
        $mapper->flush();

        $mapper->clearIdentityMap();
        $refetched = $mapper->author[1]->fetch();
        $this->assertNull($refetched->bio);
    }
}
