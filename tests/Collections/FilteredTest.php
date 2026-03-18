<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(Filtered::class)]
class FilteredTest extends TestCase
{
    #[Test]
    public function collectionCanBeCreatedStaticallyWithChildren(): void
    {
        $children1 = Filtered::by('bar')->bar();
        $children2 = Filtered::by('bat')->baz()->bat();
        $coll = Collection::foo($children1, $children2)->bar();
        $this->assertInstanceOf(Collection::class, $coll);
        $this->assertInstanceOf(Collection::class, $coll->getNext());
        $this->assertInstanceOf(Filtered::class, $children1);
        $this->assertInstanceOf(Filtered::class, $children2);
        $this->assertTrue($coll->hasChildren());
        $this->assertEquals(2, count($coll->getChildren()));
        $this->assertEquals(['bar'], $children1->getFilters());
        $this->assertEquals(['bat'], $children2->getFilters());
    }

    #[Test]
    public function callStaticShouldCreateFilteredCollectionWithName(): void
    {
        $coll = Filtered::items();
        $this->assertInstanceOf(Filtered::class, $coll);
        $this->assertEquals('items', $coll->getName());
        $this->assertEquals([], $coll->getFilters());
    }

    #[Test]
    public function isIdentifierOnlyReturnsTrueForIdentifierOnlyFilter(): void
    {
        $coll = Filtered::by(Filtered::IDENTIFIER_ONLY)->post();
        $this->assertTrue($coll->isIdentifierOnly());
    }

    #[Test]
    public function isIdentifierOnlyReturnsFalseForNamedFilters(): void
    {
        $coll = Filtered::by('title')->post();
        $this->assertFalse($coll->isIdentifierOnly());
    }

    #[Test]
    public function isIdentifierOnlyReturnsFalseForEmptyFilters(): void
    {
        $coll = Filtered::post();
        $this->assertFalse($coll->isIdentifierOnly());
    }
}
