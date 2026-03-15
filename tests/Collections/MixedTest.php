<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(Mix::class)]
class MixedTest extends TestCase
{
    #[Test]
    public function collectionCanBeCreatedStaticallyWithChildren(): void
    {
        $children1 = Mix::with(['foo' => ['bar']])->bar();
        $children2 = Mix::with(['bat' => ['bar']])->baz()->bat();
        $coll = Collection::foo($children1, $children2)->bar();
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $coll->getNext());
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $children1);
        $this->assertInstanceOf('Respect\Data\Collections\Collection', $children2);
        $this->assertTrue($coll->hasChildren());
        $this->assertEquals(2, count($coll->getChildren()));
        $this->assertEquals(['foo' => ['bar']], $children1->getExtra('mixins'));
        $this->assertEquals(['bat' => ['bar']], $children2->getExtra('mixins'));
    }
}
