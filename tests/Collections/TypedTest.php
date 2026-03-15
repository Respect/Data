<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Typed::class)]
class TypedTest extends TestCase
{
    #[Test]
    public function collection_can_be_created_statically_with_children(): void
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
