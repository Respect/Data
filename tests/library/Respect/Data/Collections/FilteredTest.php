<?php

namespace Respect\Data\Collections;

class FilteredTest extends \PHPUnit_Framework_TestCase
{
    function test_collection_can_be_created_statically_with_children()
    {
        $children_1 = Filtered::by('bar')->bar();
        $children_2 = Filtered::by('bat')->baz()->bat();
        $coll = Collection::foo($children_1, $children_2);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertInstanceOf('Respect\Data\Collections\Filtered', $children_1);
        $this->assertInstanceOf('Respect\Data\Collections\Filtered', $children_2);
        $this->assertTrue($coll->hasChildren());
        $this->assertEquals(2, count($coll->getChildren()));
        $this->assertEquals(array('bar'), $children_1->getFilters());
        $this->assertEquals(array('bat'), $children_2->getFilters());
    }
   
}
