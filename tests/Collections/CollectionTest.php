<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\AbstractMapper;

#[CoversClass(Collection::class)]
class CollectionTest extends TestCase
{
    #[Test]
    public function collection_can_be_created_statically_with_just_a_name(): void
    {
        $coll = Collection::fooBarName();
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
    }

    #[Test]
    public function collection_can_be_created_statically_with_children(): void
    {
        $children_1 = Collection::bar();
        $children_2 = Collection::baz();
        $coll = Collection::foo($children_1, $children_2);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertTrue($coll->hasChildren());
        $this->assertEquals(2, count($coll->getChildren()));
    }

    #[Test]
    public function collection_can_be_created_statically_with_condition(): void
    {
        $coll = Collection::fooBar(42);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertEquals(42, $coll->getCondition());
    }

    #[Test]
    public function multiple_conditions_on_static_creation_leaves_the_last(): void
    {
        $coll = Collection::fooBar(42, 'Other dominant condition!!!');
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertEquals(
            'Other dominant condition!!!', $coll->getCondition()
        );
    }

    #[Test]
    public function object_constructor_should_set_object_attributes(): void
    {
        $coll = new Collection('some_irrelevant_name');
        $ref = new \ReflectionObject($coll);
        $prop = $ref->getProperty('last');
        $this->assertSame($coll, $prop->getValue($coll), 'Constructing it manually should set last item as self');
        $this->assertEquals(
            array(), $coll->getCondition(),
            'Default condition should be an empty array'
        );
        $this->assertEquals('some_irrelevant_name', $coll->getName());
    }

    #[Test]
    public function object_constructor_with_condition_should_set_it(): void
    {
        $coll = new Collection('some_irrelevant_name', 123);
        $this->assertEquals(123, $coll->getCondition());
    }

    #[Test]
    public function dynamic_getter_should_stack_collection(): void
    {
        $coll = new Collection('hi');
        $coll->some_test;
        $this->assertEquals(
            'some_test', $coll->getNextName(),
            'First time should change next item'
        );
    }

    #[Test]
    public function dynamic_getter_should_chain_collection(): void
    {
        $coll = new Collection('hi');
        $coll->some_test;
        $this->assertEquals(
            'some_test', $coll->getNextName(),
            'First time should change next item'
        );
        $coll->another_test;
        $this->assertEquals(
            'some_test', $coll->getNextName(),
            'The next item on a chain should never be changed after first time'
        );
    }

    #[Test]
    public function setting_condition_via_dynamic_offset_should_use_last_node(): void
    {
        $foo = Collection::foo()->bar->baz[42];
        $bar = $foo->getNext();
        $baz = $bar->getNext();
        $this->assertEmpty($foo->getCondition());
        $this->assertEmpty($bar->getCondition());
        $this->assertEquals(42, $baz->getCondition());
    }

    #[Test]
    public function dynamic_method_call_should_accept_children(): void
    {
        $coll = new Collection('some_name');
        $coll->foo_bar(
            Collection::some(),
            Collection::children(),
            Collection::here()
        );
        $this->assertEquals(3, count($coll->getNext()->getChildren()));
    }

    #[Test]
    public function addChild_should_set_children_object_properties(): void
    {
        $coll = new Collection('foo_collection');
        $coll->addChild(new Collection('bar_child'));
        $child = $coll->getChildren();
        $child = reset($child);
        $this->assertEquals(false, $child->isRequired());
        $this->assertEquals($coll->getName(), $child->getParentName());
    }

    #[Test]
    public function children_should_make_hasMore_true(): void
    {
        $coll = Collection::foo(Collection::this_is_a_children());
        $this->assertTrue($coll->hasMore());
    }

    #[Test]
    public function chaining_should_make_hasMore_true(): void
    {
        $coll = Collection::foo()->barChain;
        $this->assertTrue($coll->hasMore());
    }

    #[Test]
    public function array_offsetSet_should_NOT_do_anything(): void
    {
        $touched = Collection::foo()->bar;
        $touched['magic'] = 'FOOOO';
        $untouched = Collection::foo()->bar;
        $this->assertEquals($untouched, $touched);
    }

    #[Test]
    public function array_offsetUnset_should_NOT_do_anything(): void
    {
        $touched = Collection::foo()->bar;
        unset($touched['magic']);
        unset($touched['bar']);
        $untouched = Collection::foo()->bar;
        $this->assertEquals($untouched, $touched);
    }

    #[Test]
    public function persist_should_persist_on_attached_mapper(): void
    {
        $persisted = new \stdClass();
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
                   ->method('persist')
                   ->with($persisted, $collection)
                   ->willReturn(true);
        $collection->setMapper($mapperMock);
        $collection->persist($persisted);
    }

    #[Test]
    public function remove_should_persist_on_attached_mapper(): void
    {
        $removed = new \stdClass();
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
                   ->method('remove')
                   ->with($removed, $collection)
                   ->willReturn(true);
        $collection->setMapper($mapperMock);
        $collection->remove($removed);
    }

    #[Test]
    public function fetch_should_persist_on_attached_mapper(): void
    {
        $result = 'result stub';
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
                   ->method('fetch')
                   ->with($collection)
                   ->willReturn($result);
        $collection->setMapper($mapperMock);
        $collection->fetch();
    }

    #[Test]
    public function fetch_should_persist_on_attached_mapper_with_extra_param(): void
    {
        $result = 'result stub';
        $extra = 'extra stub';
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
                   ->method('fetch')
                   ->with($collection, $extra)
                   ->willReturn($result);
        $collection->setMapper($mapperMock);
        $collection->fetch($extra);
    }

    #[Test]
    public function fetchAll_should_persist_on_attached_mapper(): void
    {
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
                   ->method('fetchAll')
                   ->with($collection)
                   ->willReturn([]);
        $collection->setMapper($mapperMock);
        $collection->fetchAll();
    }

    #[Test]
    public function fetchAll_should_persist_on_attached_mapper_with_extra_param(): void
    {
        $extra = 'extra stub';
        $collection = new Collection('name_whatever');
        $mapperMock = $this->createMock(AbstractMapper::class);
        $mapperMock->expects($this->once())
                   ->method('fetchAll')
                   ->with($collection, $extra)
                   ->willReturn([]);
        $collection->setMapper($mapperMock);
        $collection->fetchAll($extra);
    }

    #[Test]
    public function array_offsetExists_should_NOT_do_anything(): void
    {
        $touched = Collection::foo()->bar;
        $this->assertFalse(isset($touched['magic']));
        $untouched = Collection::foo()->bar;
        $this->assertEquals($untouched, $touched);
    }

    #[Test]
    public function persist_on_collection_should_exception_if_mapper_dont_exist(): void
    {
        $this->expectException(\RuntimeException::class);
        Collection::foo()->persist(new \stdClass);
    }

    #[Test]
    public function remove_on_collection_should_exception_if_mapper_dont_exist(): void
    {
        $this->expectException(\RuntimeException::class);
        Collection::foo()->remove(new \stdClass);
    }

    #[Test]
    public function fetch_on_collection_should_exception_if_mapper_dont_exist(): void
    {
        $this->expectException(\RuntimeException::class);
        Collection::foo()->fetch();
    }

    #[Test]
    public function fetchAll_on_collection_should_exception_if_mapper_dont_exist(): void
    {
        $this->expectException(\RuntimeException::class);
        Collection::foo()->fetchAll();
    }
}
