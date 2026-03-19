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
        $children1 = Filtered::bar('bar');
        $children2 = Filtered::baz('bat')->bat();
        $coll = Collection::foo($children1, $children2)->bar();
        $this->assertInstanceOf(Collection::class, $coll);
        $this->assertInstanceOf(Collection::class, $coll->next);
        $this->assertInstanceOf(Filtered::class, $children1);
        $this->assertInstanceOf(Filtered::class, $children2);
        $this->assertTrue($coll->hasChildren);
        $this->assertEquals(2, count($coll->children));
        $this->assertEquals(['bar'], $children1->filters);
        $this->assertEquals(['bat'], $children2->filters);
    }

    #[Test]
    public function callStaticShouldCreateFilteredCollectionWithName(): void
    {
        $coll = Filtered::items();
        $this->assertInstanceOf(Filtered::class, $coll);
        $this->assertEquals('items', $coll->name);
        $this->assertEquals([], $coll->filters);
    }

    #[Test]
    public function isIdentifierOnlyReturnsTrueForIdentifierOnlyFilter(): void
    {
        $coll = Filtered::post(Filtered::IDENTIFIER_ONLY);
        $this->assertTrue($coll->identifierOnly);
    }

    #[Test]
    public function isIdentifierOnlyReturnsFalseForNamedFilters(): void
    {
        $coll = Filtered::post('title');
        $this->assertFalse($coll->identifierOnly);
    }

    #[Test]
    public function isIdentifierOnlyReturnsFalseForEmptyFilters(): void
    {
        $coll = Filtered::post();
        $this->assertFalse($coll->identifierOnly);
    }
}
