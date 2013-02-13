<?php

namespace Respect\Data\Collections;

class TypedTest extends \PHPUnit_Framework_TestCase
{
    function test_collection_can_be_created_statically_with_children()
    {
        $children_1 = Typed::by('a')->bar();
        $children_2 = Typed::by('b')->baz()->bat();
        $coll = Collection::foo($children_1, $children_2)->bar();
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll->getNext());
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $children_1);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $children_2);
        $this->assertTrue($coll->hasChildren());
        $this->assertEquals(2, count($coll->getChildren()));
        $this->assertEquals('a', $children_1->getExtra('type'));
        $this->assertEquals('b', $children_2->getExtra('type'));
    }
   
}
