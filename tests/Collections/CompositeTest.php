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
        $children1 = Composite::bar(['foo' => ['bar']]);
        $children2 = Composite::baz(['bat' => ['bar']]);
        $coll = Collection::foo([$children1, $children2]);
        $this->assertInstanceOf(Collection::class, $coll);
        $this->assertInstanceOf(Composite::class, $children1);
        $this->assertInstanceOf(Composite::class, $children2);
        $this->assertTrue($coll->hasChildren);
        $this->assertEquals(2, count($coll->with));
        $this->assertEquals(['foo' => ['bar']], $children1->compositions);
        $this->assertEquals(['bat' => ['bar']], $children2->compositions);
    }

    #[Test]
    public function derivePreservesCompositions(): void
    {
        $original = Composite::post(['comment' => ['text']]);
        $derived = $original->derive(with: [Collection::author()], filter: 5);

        $this->assertInstanceOf(Composite::class, $derived);
        $this->assertEquals('post', $derived->name);
        $this->assertEquals(['comment' => ['text']], $derived->compositions);
        $this->assertCount(1, $derived->with);
        $this->assertEquals('author', $derived->with[0]->name);
        $this->assertEquals(5, $derived->filter);
    }

    #[Test]
    public function callStaticShouldCreateCompositeCollectionWithName(): void
    {
        $coll = Composite::items();
        $this->assertInstanceOf(Composite::class, $coll);
        $this->assertEquals('items', $coll->name);
        $this->assertEquals([], $coll->compositions);
    }
}
