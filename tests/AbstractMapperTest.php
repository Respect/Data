<?php

declare(strict_types=1);

namespace Respect\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Respect\Data\Collections\Collection;

#[CoversClass(AbstractMapper::class)]
class AbstractMapperTest extends TestCase
{
    protected AbstractMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new class extends AbstractMapper {
            public function flush(): void
            {
            }

            public function fetch(Collection $collection, mixed $extra = null): mixed
            {
                return false;
            }

            /** @return array<int, mixed> */
            public function fetchAll(Collection $collection, mixed $extra = null): array
            {
                return [];
            }
        };
    }

    #[Test]
    public function registerCollectionShouldAddCollectionToPool(): void
    {
        $coll = Collection::foo();
        $this->mapper->registerCollection('my_alias', $coll);

        $ref = new ReflectionObject($this->mapper);
        $prop = $ref->getProperty('collections');
        $this->assertContains($coll, $prop->getValue($this->mapper));

        $this->assertEquals($coll, $this->mapper->my_alias);
    }

    #[Test]
    public function magicSetterShouldAddCollectionToPool(): void
    {
        $coll = Collection::foo();
        $this->mapper->my_alias = $coll;

        $ref = new ReflectionObject($this->mapper);
        $prop = $ref->getProperty('collections');
        $this->assertContains($coll, $prop->getValue($this->mapper));

        $this->assertEquals($coll, $this->mapper->my_alias);
    }

    #[Test]
    public function magicCallShouldBypassToCollection(): void
    {
        $collection = $this->mapper->foo()->bar()->baz();
        $expected = Collection::foo();
        $expected->setMapper($this->mapper);
        $this->assertEquals($expected->bar->baz, $collection);
    }

    #[Test]
    public function magicGetterShouldBypassToCollection(): void
    {
        $collection = $this->mapper->foo->bar->baz;
        $expected = Collection::foo();
        $expected->setMapper($this->mapper);
        $this->assertEquals($expected->bar->baz, $collection);
    }
}
