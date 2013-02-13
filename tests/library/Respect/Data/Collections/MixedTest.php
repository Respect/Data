<?php

namespace Respect\Data\Collections;

class MixedTest extends \PHPUnit_Framework_TestCase
{
    function test_collection_can_be_created_statically_with_children()
    {
        $children_1 = Mixed::with(array('foo' => array('bar')))->bar();
        $children_2 = Mixed::with(array('bat' => array('bar')))->baz()->bat();
        $coll = Collection::foo($children_1, $children_2)->bar();
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll->getNext());
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $children_1);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $children_2);
        $this->assertTrue($coll->hasChildren());
        $this->assertEquals(2, count($coll->getChildren()));
        $this->assertEquals(array('foo' => array('bar')), $children_1->getExtra('mixins'));
        $this->assertEquals(array('bat' => array('bar')), $children_2->getExtra('mixins'));
    }
   
}
