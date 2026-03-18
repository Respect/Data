<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(Composite::class)]
class CompositeTest extends TestCase
{
    #[Test]
    public function collectionCanBeCreatedStaticallyWithChildren(): void
    {
        $children1 = Composite::with(['foo' => ['bar']])->bar();
        $children2 = Composite::with(['bat' => ['bar']])->baz()->bat();
        $coll = Collection::foo($children1, $children2)->bar();
        $this->assertInstanceOf(Collection::class, $coll);
        $this->assertInstanceOf(Collection::class, $coll->getNext());
        $this->assertInstanceOf(Composite::class, $children1);
        $this->assertInstanceOf(Composite::class, $children2);
        $this->assertTrue($coll->hasChildren());
        $this->assertEquals(2, count($coll->getChildren()));
        $this->assertEquals(['foo' => ['bar']], $children1->getCompositions());
        $this->assertEquals(['bat' => ['bar']], $children2->getCompositions());
    }

    #[Test]
    public function callStaticShouldCreateCompositeCollectionWithName(): void
    {
        $coll = Composite::items();
        $this->assertInstanceOf(Composite::class, $coll);
        $this->assertEquals('items', $coll->getName());
        $this->assertEquals([], $coll->getCompositions());
    }
}
