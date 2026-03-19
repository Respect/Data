<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\AbstractMapper;
use RuntimeException;
use stdClass;

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
        $persisted = new stdClass();
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
            ->method('persist')
            ->with($persisted, $collection)
            ->willReturn(true);
        $collection->mapper = $mapperMock;
        $collection->persist($persisted);
    }

    #[Test]
    public function removeShouldPersistOnAttachedMapper(): void
    {
        $removed = new stdClass();
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
        Collection::foo()->persist(new stdClass());
    }

    #[Test]
    public function removeOnCollectionShouldExceptionIfMapperDontExist(): void
    {
        $this->expectException(RuntimeException::class);
        Collection::foo()->remove(new stdClass());
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
    public function usingShouldCreateCollectionWithCondition(): void
    {
        $coll = Collection::using(42);
        $this->assertInstanceOf(Collection::class, $coll);
        $this->assertEquals(42, $coll->condition);
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
    public function getNextShouldReturnNullWhenNone(): void
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
}
