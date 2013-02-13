<?php

namespace Respect\Data\Collections;

class CollectionTest extends \PHPUnit_Framework_TestCase
{
    function test_collection_can_be_created_statically_with_just_a_name()
    {
        $coll = Collection::fooBarName();
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
    }

    function test_collection_can_be_created_statically_with_children()
    {
        $children_1 = Collection::bar();
        $children_2 = Collection::baz();
        $coll = Collection::foo($children_1, $children_2);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertTrue($coll->hasChildren());
        $this->assertEquals(2, count($coll->getChildren()));
    }

    function test_collection_can_be_created_statically_with_condition()
    {
        $coll = Collection::fooBar(42);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertAttributeEquals(42, 'condition', $coll);
    }

    function test_multiple_conditions_on_static_creation_leaves_the_last()
    {
        $coll = Collection::fooBar(42, 'Other dominant condition!!!');
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertEquals(
            'Other dominant condition!!!', $coll->getCondition()
        );
    }

    function test_object_constructor_should_set_object_attributes()
    {
        $coll = new Collection('some_irrelevant_name');
        $this->assertAttributeSame(
            $coll, 'last', $coll,
            'Constructing it manually should set last item as self'
        );
        $this->assertEquals(
            array(), $coll->getCondition(),
            'Default condition should be an empty array'
        );
        $this->assertEquals('some_irrelevant_name', $coll->getName());
    }

    function test_object_constructor_with_condition_should_set_it()
    {
        $coll = new Collection('some_irrelevant_name', 123);
        $this->assertEquals(123, $coll->getCondition());
    }

    function test_dynamic_getter_should_stack_collection()
    {
        $coll = new Collection('hi');
        $coll->some_test;
        $this->assertEquals(
            'some_test', $coll->getNextName(),
            'First time should change next item'
        );
    }

    function test_dynamic_getter_should_chain_collection()
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

    function test_setting_condition_via_dynamic_offset_should_use_last_node()
    {
        $foo = Collection::foo()->bar->baz[42];
        $bar = $foo->getNext();
        $baz = $bar->getNext();
        $this->assertEmpty($foo->getCondition());
        $this->assertEmpty($bar->getCondition());
        $this->assertEquals(42, $baz->getCondition());
    }

    function test_dynamic_method_call_should_accept_children()
    {
        $coll = new Collection('some_name');
        $coll->foo_bar(
            Collection::some(),
            Collection::children(),
            Collection::here()
        );
        $this->assertEquals(3, count($coll->getNext()->getChildren()));
    }

    function test_addChild_should_set_children_object_properties()
    {
        $coll = new Collection('foo_collection');
        $coll->addChild(new Collection('bar_child'));
        $child = $coll->getChildren();
        $child = reset($child);
        $this->assertEquals(false, $child->isRequired());
        $this->assertEquals($coll->getName(), $child->getParentName());
    }

    function test_children_should_make_hasMore_true()
    {
        $coll = Collection::foo(Collection::this_is_a_children());
        $this->assertTrue($coll->hasMore());
    }

    function test_chaining_should_make_hasMore_true()
    {
        $coll = Collection::foo()->barChain;
        $this->assertTrue($coll->hasMore());
    }

    function test_array_offsetSet_should_NOT_do_anything()
    {
        $touched = Collection::foo()->bar;
        $touched['magic'] = 'FOOOO';
        $untouched = Collection::foo()->bar;
        $this->assertEquals($untouched, $touched);
    }

    function test_array_offsetUnset_should_NOT_do_anything()
    {
        $touched = Collection::foo()->bar;
        unset($touched['magic']);
        unset($touched['bar']);
        $untouched = Collection::foo()->bar;
        $this->assertEquals($untouched, $touched);
    }

    function test_persist_should_persist_on_attached_mapper()
    {
        $persisted = new \stdClass();
        $result = 'result stub';
        $collection = new Collection('name_whatever');
        $mapperMock = $this->getMock('Respect\Data\AbstractMapper', array('persist', 'createStatement', 'flush'));
        $mapperMock->expects($this->once())
                   ->method('persist')
                   ->with($persisted, $collection)
                   ->will($this->returnValue($result));
        $collection->setMapper($mapperMock);
        $collection->persist($persisted);
    }

    function test_remove_should_persist_on_attached_mapper()
    {
        $removed = new \stdClass();
        $result = 'result stub';
        $collection = new Collection('name_whatever');
        $mapperMock = $this->getMock('Respect\Data\AbstractMapper', array('remove', 'createStatement', 'flush'));
        $mapperMock->expects($this->once())
                   ->method('remove')
                   ->with($removed, $collection)
                   ->will($this->returnValue($result));
        $collection->setMapper($mapperMock);
        $collection->remove($removed);
    }

    function test_fetch_should_persist_on_attached_mapper()
    {
        $result = 'result stub';
        $collection = new Collection('name_whatever');
        $mapperMock = $this->getMock('Respect\Data\AbstractMapper', array('fetch', 'createStatement', 'flush'));
        $mapperMock->expects($this->once())
                   ->method('fetch')
                   ->with($collection)
                   ->will($this->returnValue($result));
        $collection->setMapper($mapperMock);
        $collection->fetch();
    }

    function test_fetch_should_persist_on_attached_mapper_with_extra_param()
    {
        $result = 'result stub';
        $extra = 'extra stub';
        $collection = new Collection('name_whatever');
        $mapperMock = $this->getMock('Respect\Data\AbstractMapper', array('fetch', 'createStatement', 'flush'));
        $mapperMock->expects($this->once())
                   ->method('fetch', $extra)
                   ->with($collection)
                   ->will($this->returnValue($result));
        $collection->setMapper($mapperMock);
        $collection->fetch($extra);
    }
    function test_fetchAll_should_persist_on_attached_mapper()
    {
        $result = 'result stub';
        $collection = new Collection('name_whatever');
        $mapperMock = $this->getMock('Respect\Data\AbstractMapper', array('fetchAll', 'createStatement', 'flush'));
        $mapperMock->expects($this->once())
                   ->method('fetchAll')
                   ->with($collection)
                   ->will($this->returnValue($result));
        $collection->setMapper($mapperMock);
        $collection->fetchAll();
    }

    function test_fetchAll_should_persist_on_attached_mapper_with_extra_param()
    {
        $result = 'result stub';
        $extra = 'extra stub';
        $collection = new Collection('name_whatever');
        $mapperMock = $this->getMock('Respect\Data\AbstractMapper', array('fetchAll', 'createStatement', 'flush'));
        $mapperMock->expects($this->once())
                   ->method('fetchAll', $extra)
                   ->with($collection)
                   ->will($this->returnValue($result));
        $collection->setMapper($mapperMock);
        $collection->fetchAll($extra);
    }

    function test_array_offsetExists_should_NOT_do_anything()
    {
        $touched = Collection::foo()->bar;
        $this->assertFalse(isset($touched['magic']));
        $untouched = Collection::foo()->bar;
        $this->assertEquals($untouched, $touched);
    }

    function test_persist_on_collection_should_exception_if_mapper_dont_exist()
    {
        $this->setExpectedException('RuntimeException');
        Collection::foo()->persist(new \stdClass);
    }

    function test_remove_on_collection_should_exception_if_mapper_dont_exist()
    {
        $this->setExpectedException('RuntimeException');
        Collection::foo()->remove(new \stdClass);
    }

    function test_fetch_on_collection_should_exception_if_mapper_dont_exist()
    {
        $this->setExpectedException('RuntimeException');
        Collection::foo()->fetch();
    }

    function test_fetchAll_on_collection_should_exception_if_mapper_dont_exist()
    {
        $this->setExpectedException('RuntimeException');
        Collection::foo()->fetchAll();
    }


    
}
