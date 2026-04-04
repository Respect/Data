<?php

declare(strict_types=1);

namespace Respect\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

#[CoversClass(ScopeIterator::class)]
class ScopeIteratorTest extends TestCase
{
    #[Test]
    public function staticBuilderShouldCreateRecursiveIterator(): void
    {
        $this->assertInstanceOf(
            'RecursiveIteratorIterator',
            ScopeIterator::recursive(Scope::foo()),
        );
    }

    #[Test]
    public function constructingShouldAcceptScopesOrArrays(): void
    {
        $iterator = new ScopeIterator(Scope::foo());
        $iterator2 = new ScopeIterator([Scope::foo()]);
        $this->assertEquals($iterator, $iterator2);
    }

    #[Test]
    public function keyShouldTrackNameCounts(): void
    {
        $i = new ScopeIterator(Scope::foo());
        $this->assertEquals('foo', $i->key());
        $this->assertEquals('foo2', $i->key());
        $this->assertEquals('foo3', $i->key());
    }

    #[Test]
    public function hasChildrenConsiderEmpties(): void
    {
        $coll = Scope::foo();
        $iterator = new ScopeIterator($coll);
        $this->assertFalse($iterator->hasChildren());
    }

    #[Test]
    public function hasChildrenUseScopeChildren(): void
    {
        $coll = Scope::foo([Scope::bar()]);
        $iterator = new ScopeIterator($coll);
        $this->assertTrue($iterator->hasChildren());
    }

    #[Test]
    public function getChildrenConsiderEmpties(): void
    {
        $coll = Scope::foo();
        $iterator = new ScopeIterator($coll);
        $this->assertEquals(new ScopeIterator(), $iterator->getChildren());
    }

    #[Test]
    public function getChildrenUseScopeWith(): void
    {
        $coll = Scope::foo([Scope::bar(), Scope::baz()]);
        $items = iterator_to_array(ScopeIterator::recursive($coll));
        $names = [];
        foreach ($items as $item) {
            $names[] = $item->name;
        }

        $this->assertContains('bar', $names);
        $this->assertContains('baz', $names);
    }

    #[Test]
    public function recursiveTraversalShouldVisitNestedChildren(): void
    {
        $coll = Scope::foo([Scope::bar([Scope::baz()])]);
        $items = iterator_to_array(ScopeIterator::recursive($coll));
        $this->assertCount(3, $items);
    }
}
