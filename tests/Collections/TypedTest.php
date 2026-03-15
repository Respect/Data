<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(Typed::class)]
class TypedTest extends TestCase
{
    #[Test]
    public function collectionCanBeCreatedStaticallyWithChildren(): void
    {
        $children1 = Typed::by('a')->bar();
        $children2 = Typed::by('b')->baz()->bat();
        $coll = Collection::foo($children1, $children2)->bar();
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll->getNext());
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $children1);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $children2);
        $this->assertTrue($coll->hasChildren());
        $this->assertEquals(2, count($coll->getChildren()));
        $this->assertEquals('a', $children1->getExtra('type'));
        $this->assertEquals('b', $children2->getExtra('type'));
    }

    #[Test]
    public function callStaticShouldCreateTypedCollectionWithName(): void
    {
        $coll = Typed::items();
        $this->assertInstanceOf(Typed::class, $coll);
        $this->assertEquals('items', $coll->getName());
        $this->assertEquals('', $coll->getExtra('type'));
    }
}
