<?php

declare(strict_types=1);

namespace Respect\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Respect\Data\Collections\Collection;
use Respect\Data\Styles\CakePHP;
use Respect\Data\Styles\Standard;
use SplObjectStorage;
use stdClass;

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
        /** @var array<string, Collection> $collections */
        $collections = $prop->getValue($this->mapper);
        $this->assertContains($coll, $collections);

        $this->assertEquals($coll, $this->mapper->my_alias);
    }

    #[Test]
    public function magicSetterShouldAddCollectionToPool(): void
    {
        $coll = Collection::foo();
        $this->mapper->my_alias = $coll;

        $ref = new ReflectionObject($this->mapper);
        $prop = $ref->getProperty('collections');
        /** @var array<string, Collection> $collections */
        $collections = $prop->getValue($this->mapper);
        $this->assertContains($coll, $collections);

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

    #[Test]
    public function getStyleShouldReturnDefaultStandard(): void
    {
        $style = $this->mapper->getStyle();
        $this->assertInstanceOf(Standard::class, $style);
    }

    #[Test]
    public function getStyleShouldReturnSameInstanceOnSubsequentCalls(): void
    {
        $style1 = $this->mapper->getStyle();
        $style2 = $this->mapper->getStyle();
        $this->assertSame($style1, $style2);
    }

    #[Test]
    public function setStyleShouldChangeStyle(): void
    {
        $style = new CakePHP();
        $result = $this->mapper->setStyle($style);
        $this->assertSame($style, $this->mapper->getStyle());
        $this->assertSame($this->mapper, $result);
    }

    #[Test]
    public function persistShouldMarkObjectAsTracked(): void
    {
        $entity = new stdClass();
        $collection = Collection::foo();
        $this->mapper->persist($entity, $collection);
        $this->assertTrue($this->mapper->isTracked($entity));
    }

    #[Test]
    public function persistAlreadyTrackedShouldReturnTrue(): void
    {
        $entity = new stdClass();
        $collection = Collection::foo();
        $this->mapper->markTracked($entity, $collection);
        $result = $this->mapper->persist($entity, $collection);
        $this->assertTrue($result);
    }

    #[Test]
    public function removeShouldMarkObjectAsTracked(): void
    {
        $entity = new stdClass();
        $collection = Collection::foo();
        $result = $this->mapper->remove($entity, $collection);
        $this->assertTrue($result);
        $this->assertTrue($this->mapper->isTracked($entity));
    }

    #[Test]
    public function removeAlreadyTrackedShouldReturnTrue(): void
    {
        $entity = new stdClass();
        $collection = Collection::foo();
        $this->mapper->markTracked($entity, $collection);
        $result = $this->mapper->remove($entity, $collection);
        $this->assertTrue($result);
    }

    #[Test]
    public function isTrackedShouldReturnFalseForUntrackedEntity(): void
    {
        $this->assertFalse($this->mapper->isTracked(new stdClass()));
    }

    #[Test]
    public function markTrackedShouldReturnTrue(): void
    {
        $entity = new stdClass();
        $collection = Collection::foo();
        $this->assertTrue($this->mapper->markTracked($entity, $collection));
    }

    #[Test]
    public function resetShouldClearChangedRemovedAndNew(): void
    {
        $entity = new stdClass();
        $collection = Collection::foo();
        $this->mapper->persist($entity, $collection);
        $this->mapper->remove($entity, $collection);
        $this->mapper->reset();

        $ref = new ReflectionObject($this->mapper);

        $newProp = $ref->getProperty('new');
        /** @var SplObjectStorage<object, mixed> $newStorage */
        $newStorage = $newProp->getValue($this->mapper);
        $this->assertCount(0, $newStorage);

        $changedProp = $ref->getProperty('changed');
        /** @var SplObjectStorage<object, mixed> $changedStorage */
        $changedStorage = $changedProp->getValue($this->mapper);
        $this->assertCount(0, $changedStorage);

        $removedProp = $ref->getProperty('removed');
        /** @var SplObjectStorage<object, mixed> $removedStorage */
        $removedStorage = $removedProp->getValue($this->mapper);
        $this->assertCount(0, $removedStorage);
    }

    #[Test]
    public function issetShouldReturnTrueForRegisteredCollection(): void
    {
        $coll = Collection::foo();
        $this->mapper->registerCollection('my_alias', $coll);
        $this->assertTrue(isset($this->mapper->my_alias));
    }

    #[Test]
    public function issetShouldReturnFalseForUnregisteredCollection(): void
    {
        $this->assertFalse(isset($this->mapper->nonexistent));
    }

    #[Test]
    public function magicGetShouldReturnNewCollectionWhenNotRegistered(): void
    {
        $coll = $this->mapper->unregistered;
        $this->assertInstanceOf(Collection::class, $coll);
        $this->assertEquals('unregistered', $coll->getName());
    }
}
