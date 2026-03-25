<?php

declare(strict_types=1);

namespace Respect\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Respect\Data\Collections\Collection;
use Respect\Data\Collections\Filtered;
use Respect\Data\Hydrators\Nested;
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

            protected function defaultHydrator(Collection $collection): Hydrator
            {
                return new Nested();
            }
        };
    }

    #[Test]
    public function registerCollectionShouldAddCollectionToPool(): void
    {
        $coll = Collection::foo();
        $this->mapper->registerCollection('my_alias', $coll);

        $this->assertTrue(isset($this->mapper->my_alias));
        $this->assertEquals($coll, $this->mapper->my_alias);
    }

    #[Test]
    public function magicSetterShouldAddCollectionToPool(): void
    {
        $coll = Collection::foo();
        $this->mapper->my_alias = $coll;

        $this->assertTrue(isset($this->mapper->my_alias));

        $this->assertEquals($coll, $this->mapper->my_alias);
    }

    #[Test]
    public function magicCallShouldBypassToCollection(): void
    {
        $collection = $this->mapper->foo()->bar()->baz();
        $expected = Collection::foo();
        $expected->mapper = $this->mapper;
        $this->assertEquals($expected->bar->baz, $collection);
    }

    #[Test]
    public function magicGetterShouldBypassToCollection(): void
    {
        $collection = $this->mapper->foo->bar->baz;
        $expected = Collection::foo();
        $expected->mapper = $this->mapper;
        $this->assertEquals($expected->bar->baz, $collection);
    }

    #[Test]
    public function getStyleShouldReturnDefaultStandard(): void
    {
        $style = $this->mapper->style;
        $this->assertInstanceOf(Standard::class, $style);
    }

    #[Test]
    public function getStyleShouldReturnSameInstanceOnSubsequentCalls(): void
    {
        $style1 = $this->mapper->style;
        $style2 = $this->mapper->style;
        $this->assertSame($style1, $style2);
    }

    #[Test]
    public function styleShouldComeFromEntityFactory(): void
    {
        $style = new CakePHP();
        $mapper = new class (new EntityFactory(style: $style)) extends AbstractMapper {
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

            protected function defaultHydrator(Collection $collection): Hydrator
            {
                return new Nested();
            }
        };
        $this->assertSame($style, $mapper->style);
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
    public function resetShouldClearPending(): void
    {
        $entity = new stdClass();
        $collection = Collection::foo();
        $this->mapper->persist($entity, $collection);
        $this->mapper->remove($entity, $collection);
        $this->mapper->reset();

        $ref = new ReflectionObject($this->mapper);

        $pendingProp = $ref->getProperty('pending');
        /** @var SplObjectStorage<object, mixed> $pendingStorage */
        $pendingStorage = $pendingProp->getValue($this->mapper);
        $this->assertCount(0, $pendingStorage);
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
        $this->assertEquals('unregistered', $coll->name);
    }

    #[Test]
    public function hydrationWiresFkWithMatchingEntity(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Post'],
        ]);

        $comment = $mapper->comment->post->fetch();
        $this->assertIsObject($comment);
        // FK stays as its original scalar value, never overwritten with an object
        $fk = $mapper->entityFactory->get($comment, 'post_id');
        $this->assertIsNotObject($fk);
        $this->assertEquals(5, $fk);

        // Related entity goes to the derived relation property
        $post = $mapper->entityFactory->get($comment, 'post');
        $this->assertIsObject($post);
        $this->assertEquals(5, $mapper->entityFactory->get($post, 'id'));
        $this->assertEquals('Post', $mapper->entityFactory->get($post, 'title'));
    }

    #[Test]
    public function persistAfterHydrationPreservesFkAndIgnoresRelation(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Post'],
        ]);

        // Fetch with relationship — hydrates $comment->post
        $comment = $mapper->comment->post->fetch();
        $this->assertIsObject($mapper->entityFactory->get($comment, 'post'));

        // Modify and persist
        $mapper->entityFactory->set($comment, 'text', 'Updated');
        $mapper->comment->persist($comment);
        $mapper->flush();

        // Re-fetch without relationship
        $updated = $mapper->comment[1]->fetch();
        $this->assertEquals('Updated', $mapper->entityFactory->get($updated, 'text'));

        // FK stayed as scalar
        $fk = $mapper->entityFactory->get($updated, 'post_id');
        $this->assertIsNotObject($fk);
        $this->assertEquals(5, $fk);
    }

    #[Test]
    public function hydrationLeavesFkUnchangedWhenNoMatch(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 999],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Post'],
        ]);

        $comment = $mapper->comment->post->fetch();
        $this->assertIsObject($comment);
        $this->assertEquals(999, $mapper->entityFactory->get($comment, 'post_id'));
    }

    #[Test]
    public function hydrationMatchesIntFkToStringPk(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => '5', 'title' => 'Post'],
        ]);

        $comment = $mapper->comment->post->fetch();
        $this->assertIsObject($comment);
        // FK stays as int, relation goes to derived property
        $this->assertEquals(5, $mapper->entityFactory->get($comment, 'post_id'));
        $post = $mapper->entityFactory->get($comment, 'post');
        $this->assertIsObject($post);
        $this->assertEquals('5', $mapper->entityFactory->get($post, 'id'));
    }

    #[Test]
    public function callingRegisteredCollectionClonesAndAppliesCondition(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Hello'],
            ['id' => 2, 'title' => 'World'],
        ]);

        $mapper->postTitles = Filtered::posts('title');

        $conditioned = $mapper->postTitles(['id' => 2]);

        $this->assertInstanceOf(Filtered::class, $conditioned);
        $this->assertEquals('posts', $conditioned->name);
        $this->assertEquals(['title'], $conditioned->filters);
        $this->assertEquals(['id' => 2], $conditioned->condition);
        $this->assertEquals([], $mapper->postTitles->condition, 'Original collection should be unchanged');
    }

    #[Test]
    public function callingRegisteredCollectionWithoutConditionReturnsClone(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->postTitles = Filtered::posts('title');

        $clone = $mapper->postTitles();

        $this->assertInstanceOf(Filtered::class, $clone);
        $this->assertNotSame($mapper->postTitles, $clone);
        $this->assertEquals('posts', $clone->name);
        $this->assertEquals(['title'], $clone->filters);
    }

    #[Test]
    public function callingRegisteredChainedCollectionDoesNotMutateTemplate(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', []);
        $mapper->seed('comment', []);

        $mapper->commentedPosts = Collection::posts()->comment();

        $clone = $mapper->commentedPosts();
        $clone->author; // stacks 'author' onto the clone's chain

        $original = $mapper->commentedPosts;
        $this->assertNull(
            $original->next?->next,
            'Stacking on a clone should not mutate the registered collection',
        );
    }

    #[Test]
    public function filteredPersistDelegatesToParentCollection(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', []);
        $mapper->seed('author', []);
        $mapper->authorsWithPosts = Filtered::post()->author();

        $author = new stdClass();
        $author->id = null;
        $author->name = 'Test';
        $mapper->authorsWithPosts->persist($author);
        $mapper->flush();

        $fetched = $mapper->author->fetch();
        $this->assertEquals('Test', $fetched->name);
    }

    #[Test]
    public function filteredWithoutNextFallsBackToNormalPersist(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', []);

        $post = new stdClass();
        $post->id = null;
        $post->title = 'Direct';
        $mapper->post->persist($post);
        $mapper->flush();

        $fetched = $mapper->post->fetch();
        $this->assertEquals('Direct', $fetched->title);
    }

    #[Test]
    public function filteredUpdatePersistsOnlyFilteredColumns(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body'],
        ]);

        $mapper->postTitles = Filtered::post('title');
        $post = $mapper->postTitles()->fetch();
        $this->assertIsObject($post);

        $mapper->entityFactory->set($post, 'title', 'Changed');
        $mapper->postTitles()->persist($post);
        $mapper->flush();

        $fetched = $mapper->post->fetch();
        $this->assertEquals('Changed', $mapper->entityFactory->get($fetched, 'title'));
        $this->assertEquals('Body', $mapper->entityFactory->get($fetched, 'text'));
    }

    #[Test]
    public function filteredInsertPersistsOnlyFilteredColumns(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', []);

        $mapper->postTitles = Filtered::post('title');
        $post = new stdClass();
        $post->id = 1;
        $post->title = 'Partial';
        $post->text = 'Should not persist';
        $mapper->postTitles()->persist($post);
        $mapper->flush();

        $fetched = $mapper->post->fetch();
        $this->assertEquals('Partial', $mapper->entityFactory->get($fetched, 'title'));
        $this->assertNull($mapper->entityFactory->get($fetched, 'text'));
    }

    #[Test]
    public function filterColumnsPassesThroughForPlainCollection(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body'],
        ]);

        $post = $mapper->post->fetch();
        $this->assertIsObject($post);

        $mapper->entityFactory->set($post, 'title', 'Changed');
        $mapper->entityFactory->set($post, 'text', 'New Body');
        $mapper->post->persist($post);
        $mapper->flush();

        $fetched = $mapper->post->fetch();
        $this->assertEquals('Changed', $mapper->entityFactory->get($fetched, 'title'));
        $this->assertEquals('New Body', $mapper->entityFactory->get($fetched, 'text'));
    }

    #[Test]
    public function filterColumnsPassesThroughForEmptyFilters(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body'],
        ]);

        $mapper->allPosts = Filtered::post();
        $post = $mapper->allPosts()->fetch();
        $this->assertIsObject($post);

        $mapper->entityFactory->set($post, 'title', 'Changed');
        $mapper->entityFactory->set($post, 'text', 'New Body');
        $mapper->allPosts()->persist($post);
        $mapper->flush();

        $fetched = $mapper->post->fetch();
        $this->assertEquals('Changed', $mapper->entityFactory->get($fetched, 'title'));
        $this->assertEquals('New Body', $mapper->entityFactory->get($fetched, 'text'));
    }

    #[Test]
    public function filterColumnsPassesThroughForIdentifierOnly(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body'],
        ]);

        $mapper->postIds = Filtered::post(Filtered::IDENTIFIER_ONLY);
        $post = $mapper->postIds()->fetch();
        $this->assertIsObject($post);

        $mapper->entityFactory->set($post, 'title', 'Changed');
        $mapper->entityFactory->set($post, 'text', 'New Body');
        $mapper->postIds()->persist($post);
        $mapper->flush();

        $fetched = $mapper->post->fetch();
        $this->assertEquals('Changed', $mapper->entityFactory->get($fetched, 'title'));
        $this->assertEquals('New Body', $mapper->entityFactory->get($fetched, 'text'));
    }

    #[Test]
    public function fetchPopulatesIdentityMap(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
            ['id' => 2, 'title' => 'Second'],
        ]);

        $this->assertSame(0, $mapper->identityMapCount());

        $mapper->post[1]->fetch();
        $this->assertSame(1, $mapper->identityMapCount());

        $mapper->post[2]->fetch();
        $this->assertSame(2, $mapper->identityMapCount());
    }

    #[Test]
    public function fetchReturnsCachedEntityFromIdentityMap(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
        ]);

        $first = $mapper->post[1]->fetch();
        $second = $mapper->post[1]->fetch();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function fetchAllPopulatesIdentityMap(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
            ['id' => 2, 'title' => 'Second'],
        ]);

        $mapper->post->fetchAll();
        $this->assertSame(2, $mapper->identityMapCount());
    }

    #[Test]
    public function flushInsertRegistersInIdentityMap(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', []);

        $entity = new stdClass();
        $entity->title = 'New Post';
        $mapper->post->persist($entity);
        $mapper->flush();

        $this->assertSame(1, $mapper->identityMapCount());
    }

    #[Test]
    public function flushDeleteEvictsFromIdentityMap(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'To Delete'],
        ]);

        $entity = $mapper->post[1]->fetch();
        $this->assertSame(1, $mapper->identityMapCount());

        $mapper->post->remove($entity);
        $mapper->flush();

        $this->assertSame(0, $mapper->identityMapCount());
    }

    #[Test]
    public function clearIdentityMapEmptiesMap(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
        ]);

        $mapper->post[1]->fetch();
        $this->assertSame(1, $mapper->identityMapCount());

        $mapper->clearIdentityMap();
        $this->assertSame(0, $mapper->identityMapCount());
    }

    #[Test]
    public function resetDoesNotClearIdentityMap(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
        ]);

        $mapper->post[1]->fetch();
        $this->assertSame(1, $mapper->identityMapCount());

        $mapper->reset();
        $this->assertSame(1, $mapper->identityMapCount());
    }

    #[Test]
    public function pendingOperationTypes(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Existing'],
        ]);

        $ref = new ReflectionObject($mapper);
        $pendingProp = $ref->getProperty('pending');

        // persist new entity → 'insert'
        $newEntity = new stdClass();
        $newEntity->title = 'New';
        $mapper->post->persist($newEntity);

        /** @var SplObjectStorage<object, string> $pending */
        $pending = $pendingProp->getValue($mapper);
        $this->assertSame('insert', $pending[$newEntity]);

        // persist existing entity → 'update'
        $existing = $mapper->post[1]->fetch();
        $mapper->post->persist($existing);
        /** @var SplObjectStorage<object, string> $pending */
        $pending = $pendingProp->getValue($mapper);
        $this->assertSame('update', $pending[$existing]);

        // remove entity → 'delete'
        $mapper->post->remove($existing);
        /** @var SplObjectStorage<object, string> $pending */
        $pending = $pendingProp->getValue($mapper);
        $this->assertSame('delete', $pending[$existing]);
    }

    #[Test]
    public function trackedCountReflectsTrackedEntities(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
        ]);

        $this->assertSame(0, $mapper->trackedCount());

        $mapper->post[1]->fetch();
        $this->assertSame(1, $mapper->trackedCount());
    }

    #[Test]
    public function registerSkipsEntityWithNullCollectionName(): void
    {
        $mapper = new InMemoryMapper();
        $entity = new stdClass();
        $entity->id = 1;

        // Collection with null name — register should be a no-op
        $coll = new Collection();
        $mapper->persist($entity, $coll);
        $mapper->flush();

        $this->assertSame(0, $mapper->identityMapCount());
    }

    #[Test]
    public function registerSkipsEntityWithNoPkValue(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', []);

        // Entity with no 'id' property
        $entity = new stdClass();
        $entity->title = 'No PK';
        $mapper->post->persist($entity);

        // Before flush, entity has no PK — identity map should not contain it yet
        // (identity map registration happens during flush, after PK is assigned)
        $this->assertSame(0, $mapper->identityMapCount());
    }

    #[Test]
    public function deleteEvictsUsingTrackedCollection(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Test'],
        ]);

        $entity = $mapper->post[1]->fetch();
        $this->assertSame(1, $mapper->identityMapCount());

        // Remove via a different collection — flush uses the tracked one (name='post')
        $mapper->post->remove($entity);
        $mapper->flush();

        $this->assertSame(0, $mapper->identityMapCount());
    }

    #[Test]
    public function findInIdentityMapSkipsNonScalarCondition(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
        ]);

        // Populate identity map
        $mapper->post[1]->fetch();
        $this->assertSame(1, $mapper->identityMapCount());

        // fetchAll uses array/null condition — should always hit the backend
        $all = $mapper->post->fetchAll();
        $this->assertNotEmpty($all);
    }

    #[Test]
    public function registerSkipsEntityWithNonScalarPk(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('post', []);

        $entity = new stdClass();
        $entity->id = ['not', 'scalar'];
        $entity->title = 'Bad PK';
        $mapper->post->persist($entity);
        $mapper->flush();

        // Entity with non-scalar PK should not enter identity map
        $this->assertSame(0, $mapper->identityMapCount());
    }

    #[Test]
    public function findInIdentityMapSkipsCollectionWithChildren(): void
    {
        $mapper = new InMemoryMapper();
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Post'],
        ]);

        // Fetch with relationship (has children) — should bypass identity map
        $comment = $mapper->comment->post->fetch();
        $this->assertIsObject($comment);
    }
}
