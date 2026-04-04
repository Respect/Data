<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\EntityFactory;
use Respect\Data\Scope;

#[CoversClass(Nested::class)]
#[CoversClass(Base::class)]
class NestedTest extends TestCase
{
    private Nested $hydrator;

    private EntityFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\');
        $this->hydrator = new Nested($this->factory);
    }

    #[Test]
    public function hydrateReturnsFalseForNonArray(): void
    {
        $scope = Scope::author();

        $this->assertFalse($this->hydrator->hydrateAll(null, $scope));
        $this->assertFalse($this->hydrator->hydrateAll(false, $scope));
        $this->assertFalse($this->hydrator->hydrateAll('string', $scope));
    }

    #[Test]
    public function hydrateSingleEntity(): void
    {
        $raw = ['id' => 1, 'name' => 'Author Name'];
        $scope = Scope::author();

        $result = $this->hydrator->hydrateAll($raw, $scope);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
        $result->rewind();
        $entity = $result->current();
        $this->assertEquals(1, $this->factory->get($entity, 'id'));
        $this->assertEquals('Author Name', $this->factory->get($entity, 'name'));
        $this->assertSame($scope, $result[$entity]);
    }

    #[Test]
    public function hydrateWithNestedChild(): void
    {
        $raw = [
            'id' => 1,
            'title' => 'Post Title',
            'author' => ['id' => 5, 'name' => 'Author'],
        ];
        $scope = Scope::post([Scope::author()]);

        $result = $this->hydrator->hydrateAll($raw, $scope);

        $this->assertNotFalse($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function hydrateWithMissingNestedKeyReturnsPartial(): void
    {
        $raw = ['id' => 1, 'title' => 'Post Title'];
        $scope = Scope::post([Scope::author()]);

        $result = $this->hydrator->hydrateAll($raw, $scope);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function hydrateDeeplyNested(): void
    {
        $raw = [
            'id' => 1,
            'text' => 'Comment',
            'post' => [
                'id' => 10,
                'title' => 'Post',
                'author' => ['id' => 100, 'name' => 'Author'],
            ],
        ];
        $scope = Scope::comment([
            Scope::post([Scope::author()]),
        ]);

        $result = $this->hydrator->hydrateAll($raw, $scope);

        $this->assertNotFalse($result);
        $this->assertCount(3, $result);
    }

    #[Test]
    public function hydrateWithChildren(): void
    {
        $raw = [
            'id' => 1,
            'title' => 'Post',
            'author' => ['id' => 5, 'name' => 'Author'],
            'category' => ['id' => 3, 'label' => 'Tech'],
        ];
        $authorColl = Scope::author();
        $categoryColl = Scope::category();
        $scope = Scope::post([$authorColl, $categoryColl]);

        $result = $this->hydrator->hydrateAll($raw, $scope);

        $this->assertNotFalse($result);
        $this->assertCount(3, $result);
    }

    #[Test]
    public function hydrateScalarNestedValueIsIgnored(): void
    {
        $raw = ['id' => 1, 'author' => 'not-an-array'];
        $scope = Scope::post([Scope::author()]);

        $result = $this->hydrator->hydrateAll($raw, $scope);

        $this->assertNotFalse($result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function hydrateReturnsFalseForInvalidInput(): void
    {
        $this->assertFalse($this->hydrator->hydrate(null, Scope::author()));
    }

    #[Test]
    public function hydrateReturnsRootEntity(): void
    {
        $raw = ['id' => 1, 'name' => 'Alice'];
        $result = $this->hydrator->hydrate($raw, Scope::author());

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->factory->get($result, 'id'));
        $this->assertEquals('Alice', $this->factory->get($result, 'name'));
    }

    #[Test]
    public function wireRelationshipsSkipsChildWithNullId(): void
    {
        $raw = [
            'id' => 1,
            'title' => 'Post',
            'author' => ['name' => 'No ID'],
        ];
        $scope = Scope::post([Scope::author()]);

        $result = $this->hydrator->hydrateAll($raw, $scope);

        $this->assertNotFalse($result);
        $result->rewind();
        $post = $result->current();
        // Author has no id → wiring is skipped
        $this->assertNull($this->factory->get($post, 'author'));
    }

    #[Test]
    public function hydrateReturnsRootWithWiredRelation(): void
    {
        $raw = [
            'id' => 1,
            'title' => 'Post',
            'author' => ['id' => 5, 'name' => 'Author'],
        ];
        $scope = Scope::post([Scope::author()]);

        $result = $this->hydrator->hydrate($raw, $scope);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $this->factory->get($result, 'id'));
        $author = $this->factory->get($result, 'author');
        $this->assertIsObject($author);
        $this->assertEquals(5, $this->factory->get($author, 'id'));
    }
}
