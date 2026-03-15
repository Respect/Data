<?php

declare(strict_types=1);

namespace Respect\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\Collections\Collection;

#[CoversClass(AbstractMapper::class)]
class AbstractMapperTest extends TestCase
{
    protected AbstractMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new class extends AbstractMapper {
            protected function createStatement(Collection $fromCollection, mixed $withExtra = null): mixed
            {
                return null;
            }

            public function flush(): void
            {
            }
        };
    }

    #[Test]
    public function registerCollection_should_add_collection_to_pool(): void
    {
        $coll = Collection::foo();
        $this->mapper->registerCollection('my_alias', $coll);

        $ref = new \ReflectionObject($this->mapper);
        $prop = $ref->getProperty('collections');
        $this->assertContains($coll, $prop->getValue($this->mapper));

        $this->assertEquals($coll, $this->mapper->my_alias);
    }

    #[Test]
    public function magic_setter_should_add_collection_to_pool(): void
    {
        $coll = Collection::foo();
        $this->mapper->my_alias = $coll;

        $ref = new \ReflectionObject($this->mapper);
        $prop = $ref->getProperty('collections');
        $this->assertContains($coll, $prop->getValue($this->mapper));

        $this->assertEquals($coll, $this->mapper->my_alias);
    }

    #[Test]
    public function magic_call_should_bypass_to_collection(): void
    {
        $collection = $this->mapper->foo()->bar()->baz();
        $expected = Collection::foo();
        $expected->setMapper($this->mapper);
        $this->assertEquals($expected->bar->baz, $collection);
    }

    #[Test]
    public function magic_getter_should_bypass_to_collection(): void
    {
        $collection = $this->mapper->foo->bar->baz;
        $expected = Collection::foo();
        $expected->setMapper($this->mapper);
        $this->assertEquals($expected->bar->baz, $collection);
    }
}
