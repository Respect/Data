<?php

namespace Respect\Data;

use Respect\Data\Collections\Collection;

class AbstractMapperTest extends \PHPUnit_Framework_TestCase
{
    protected $mapper;

    function setUp()
    {
        parent::setUp();
        $this->mapper = $this->getMockForAbstractClass('Respect\Data\AbstractMapper');
    }

    function test_registerCollection_should_add_collection_to_pool()
    {
        $coll = Collection::foo();
        $this->mapper->registerCollection('my_alias', $coll);
        $this->assertAttributeContains($coll, 'collections', $this->mapper);
        $this->assertEquals($coll, $this->mapper->my_alias);
    }

    function test_magic_setter_should_add_collection_to_pool()
    {
        $coll = Collection::foo();
        $this->mapper->my_alias = $coll;
        $this->assertAttributeContains($coll, 'collections', $this->mapper);
        $this->assertEquals($coll, $this->mapper->my_alias);
    }

    function test_magic_call_should_bypass_to_collection()
    {
        $collection = $this->mapper->foo()->bar()->baz();
        $expected = Collection::foo();
        $expected->setMapper($this->mapper);
        $this->assertEquals($expected->bar->baz, $collection);
    }
    function test_magic_getter_should_bypass_to_collection()
    {
        $collection = $this->mapper->foo->bar->baz;
        $expected = Collection::foo();
        $expected->setMapper($this->mapper);
        $this->assertEquals($expected->bar->baz, $collection);
    }
}
