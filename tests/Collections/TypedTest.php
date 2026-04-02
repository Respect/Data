<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\EntityFactory;

use function count;

#[CoversClass(Typed::class)]
class TypedTest extends TestCase
{
    #[Test]
    public function collectionCanBeCreatedStaticallyWithChildren(): void
    {
        $children1 = Typed::bar('a');
        $children2 = Typed::baz('b');
        $coll = Collection::foo([$children1, $children2]);
        $this->assertInstanceOf(Collection::class, $coll);
        $this->assertInstanceOf(Typed::class, $children1);
        $this->assertInstanceOf(Typed::class, $children2);
        $this->assertTrue($coll->hasChildren);
        $this->assertEquals(2, count($coll->with));
        $this->assertEquals('a', $children1->type);
        $this->assertEquals('b', $children2->type);
    }

    #[Test]
    public function derivePreservesType(): void
    {
        $original = Typed::issues('type');
        $derived = $original->derive(with: [Collection::author()], filter: 1);

        $this->assertInstanceOf(Typed::class, $derived);
        $this->assertEquals('issues', $derived->name);
        $this->assertEquals('type', $derived->type);
        $this->assertCount(1, $derived->with);
        $this->assertEquals('author', $derived->with[0]->name);
        $this->assertEquals(1, $derived->filter);
    }

    #[Test]
    public function callStaticShouldCreateTypedCollectionWithName(): void
    {
        $coll = Typed::items();
        $this->assertInstanceOf(Typed::class, $coll);
        $this->assertEquals('items', $coll->name);
        $this->assertEquals('', $coll->type);
    }

    #[Test]
    public function resolveEntityClassReturnsDiscriminatorClass(): void
    {
        $factory = new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\');
        $coll = Typed::issues('type');
        $this->assertEquals('Respect\\Data\\Stubs\\Bug', $coll->resolveEntityClass($factory, ['type' => 'Bug']));
    }

    #[Test]
    public function resolveEntityClassFallsBackToCollectionName(): void
    {
        $factory = new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\');
        $coll = Typed::issue('type');
        $this->assertEquals('Respect\\Data\\Stubs\\Issue', $coll->resolveEntityClass($factory, []));
    }
}
