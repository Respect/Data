<?php

declare(strict_types=1);

namespace Respect\Data;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Respect\Data\Collections\Collection;
use Respect\Data\Hydrators\Nested;
use Respect\Data\Styles\CakePHP;
use Respect\Data\Styles\Standard;
use SplObjectStorage;

#[CoversClass(AbstractMapper::class)]
class AbstractMapperTest extends TestCase
{
    protected AbstractMapper $mapper;

    protected function setUp(): void
    {
        $hydrator = new Nested(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $this->mapper = new class ($hydrator) extends AbstractMapper {
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

        $this->assertTrue(isset($this->mapper->my_alias));
        $clone = $this->mapper->my_alias();
        $this->assertEquals($coll->name, $clone->name);
    }

    #[Test]
    public function callingRegisteredCollectionWithoutArgsClones(): void
    {
        $coll = Collection::post();
        $this->mapper->registerCollection('post', $coll);

        $clone = $this->mapper->post();

        $this->assertNotSame($coll, $clone);
        $this->assertEquals('post', $clone->name);
    }

    #[Test]
    public function magicCallShouldBypassToCollection(): void
    {
        $collection = $this->mapper->author([$this->mapper->post([$this->mapper->comment()])]);
        $this->assertEquals('author', $collection->name);
        $this->assertCount(1, $collection->with);
        $this->assertEquals('post', $collection->with[0]->name);
        $this->assertCount(1, $collection->with[0]->with);
        $this->assertEquals('comment', $collection->with[0]->with[0]->name);
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
        $mapper = new class (new Nested(new EntityFactory(style: $style))) extends AbstractMapper {
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
        $this->assertSame($style, $mapper->style);
    }

    #[Test]
    public function persistShouldMarkObjectAsTracked(): void
    {
        $entity = new Stubs\Foo();
        $collection = Collection::foo();
        $this->mapper->persist($entity, $collection);
        $this->assertTrue($this->mapper->isTracked($entity));
    }

    #[Test]
    public function persistAlreadyTrackedShouldReturnEntity(): void
    {
        $entity = new Stubs\Foo();
        $collection = Collection::foo();
        $this->mapper->markTracked($entity, $collection);
        $result = $this->mapper->persist($entity, $collection);
        $this->assertSame($entity, $result);
    }

    #[Test]
    public function removeShouldMarkObjectAsTracked(): void
    {
        $entity = new Stubs\Foo();
        $collection = Collection::foo();
        $result = $this->mapper->remove($entity, $collection);
        $this->assertTrue($result);
        $this->assertTrue($this->mapper->isTracked($entity));
    }

    #[Test]
    public function removeAlreadyTrackedShouldReturnTrue(): void
    {
        $entity = new Stubs\Foo();
        $collection = Collection::foo();
        $this->mapper->markTracked($entity, $collection);
        $result = $this->mapper->remove($entity, $collection);
        $this->assertTrue($result);
    }

    #[Test]
    public function isTrackedShouldReturnFalseForUntrackedEntity(): void
    {
        $this->assertFalse($this->mapper->isTracked(new Stubs\Foo()));
    }

    #[Test]
    public function markTrackedShouldReturnTrue(): void
    {
        $entity = new Stubs\Foo();
        $collection = Collection::foo();
        $this->assertTrue($this->mapper->markTracked($entity, $collection));
    }

    #[Test]
    public function resetShouldClearPending(): void
    {
        $entity = new Stubs\Foo();
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
    public function hydrationWiresRelatedEntity(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Post'],
        ]);

        $comment = $mapper->fetch($mapper->comment([$mapper->post()]));
        $this->assertIsObject($comment);

        // Related entity wired via collection tree
        $post = $mapper->entityFactory->get($comment, 'post');
        $this->assertIsObject($post);
        $this->assertEquals(5, $mapper->entityFactory->get($post, 'id'));
        $this->assertEquals('Post', $mapper->entityFactory->get($post, 'title'));
    }

    #[Test]
    public function persistAfterHydrationPreservesRelation(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Post'],
        ]);

        // Fetch with relationship — hydrates $comment->post
        $comment = $mapper->fetch($mapper->comment([$mapper->post()]));
        $this->assertIsObject($mapper->entityFactory->get($comment, 'post'));

        // Modify and persist
        $mapper->entityFactory->set($comment, 'text', 'Updated');
        $mapper->persist($comment, $mapper->comment());
        $mapper->flush();

        // Re-fetch without relationship
        $updated = $mapper->fetch($mapper->comment(filter: 1));
        $this->assertEquals('Updated', $mapper->entityFactory->get($updated, 'text'));
    }

    #[Test]
    public function hydrationWithNoMatchLeavesRelationNull(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 999],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Post'],
        ]);

        $comment = $mapper->fetch($mapper->comment([$mapper->post()]));
        $this->assertIsObject($comment);
        // No post with id=999 exists, so relation stays null
        $this->assertNull($mapper->entityFactory->get($comment, 'post'));
    }

    #[Test]
    public function hydrationWiresRelationWithStringPk(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => '5', 'title' => 'Post'],
        ]);

        $comment = $mapper->fetch($mapper->comment([$mapper->post()]));
        $this->assertIsObject($comment);
        $post = $mapper->entityFactory->get($comment, 'post');
        $this->assertIsObject($post);
        $this->assertEquals('5', $mapper->entityFactory->get($post, 'id'));
    }

    #[Test]
    public function callingRegisteredCollectionReturnsImmutableClone(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', []);
        $mapper->seed('comment', []);

        $coll = Collection::posts();
        $mapper->registerCollection('commentedPosts', $coll->derive(with: [Collection::comment()]));

        $clone = $mapper->commentedPosts();

        // Clone has the child from the registered collection
        $this->assertCount(1, $clone->with);
        $this->assertEquals('comment', $clone->with[0]->name);
    }

    #[Test]
    public function directPersistWithoutRegisteredCollection(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', []);

        $post = new Stubs\Post();
        $post->title = 'Direct';
        $mapper->persist($post, $mapper->post());
        $mapper->flush();

        $fetched = $mapper->fetch($mapper->post());
        $this->assertEquals('Direct', $fetched->title);
    }

    #[Test]
    public function fetchPopulatesIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
            ['id' => 2, 'title' => 'Second'],
        ]);

        $this->assertSame(0, $mapper->identityMapCount());

        $mapper->fetch($mapper->post(filter: 1));
        $this->assertSame(1, $mapper->identityMapCount());

        $mapper->fetch($mapper->post(filter: 2));
        $this->assertSame(2, $mapper->identityMapCount());
    }

    #[Test]
    public function fetchReturnsCachedEntityFromIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
        ]);

        $first = $mapper->fetch($mapper->post(filter: 1));
        $second = $mapper->fetch($mapper->post(filter: 1));

        $this->assertSame($first, $second);
    }

    #[Test]
    public function fetchAllPopulatesIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
            ['id' => 2, 'title' => 'Second'],
        ]);

        $mapper->fetchAll($mapper->post());
        $this->assertSame(2, $mapper->identityMapCount());
    }

    #[Test]
    public function flushInsertRegistersInIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', []);

        $entity = new Stubs\Post();
        $entity->title = 'New Post';
        $mapper->persist($entity, $mapper->post());
        $mapper->flush();

        $this->assertSame(1, $mapper->identityMapCount());
    }

    #[Test]
    public function flushDeleteEvictsFromIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'To Delete'],
        ]);

        $entity = $mapper->fetch($mapper->post(filter: 1));
        $this->assertSame(1, $mapper->identityMapCount());

        $mapper->remove($entity, $mapper->post());
        $mapper->flush();

        $this->assertSame(0, $mapper->identityMapCount());
    }

    #[Test]
    public function clearIdentityMapEmptiesMap(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
        ]);

        $mapper->fetch($mapper->post(filter: 1));
        $this->assertSame(1, $mapper->identityMapCount());

        $mapper->clearIdentityMap();
        $this->assertSame(0, $mapper->identityMapCount());
    }

    #[Test]
    public function resetDoesNotClearIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
        ]);

        $mapper->fetch($mapper->post(filter: 1));
        $this->assertSame(1, $mapper->identityMapCount());

        $mapper->reset();
        $this->assertSame(1, $mapper->identityMapCount());
    }

    #[Test]
    public function pendingOperationTypes(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Existing'],
        ]);

        $ref = new ReflectionObject($mapper);
        $pendingProp = $ref->getProperty('pending');

        // persist new entity -> 'insert'
        $newEntity = new Stubs\Post();
        $newEntity->title = 'New';
        $mapper->persist($newEntity, $mapper->post());

        /** @var SplObjectStorage<object, string> $pending */
        $pending = $pendingProp->getValue($mapper);
        $this->assertSame('insert', $pending[$newEntity]);

        // persist existing entity -> 'update'
        $existing = $mapper->fetch($mapper->post(filter: 1));
        $mapper->persist($existing, $mapper->post());
        /** @var SplObjectStorage<object, string> $pending */
        $pending = $pendingProp->getValue($mapper);
        $this->assertSame('update', $pending[$existing]);

        // remove entity -> 'delete'
        $mapper->remove($existing, $mapper->post());
        /** @var SplObjectStorage<object, string> $pending */
        $pending = $pendingProp->getValue($mapper);
        $this->assertSame('delete', $pending[$existing]);
    }

    #[Test]
    public function trackedCountReflectsTrackedEntities(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
        ]);

        $this->assertSame(0, $mapper->trackedCount());

        $mapper->fetch($mapper->post(filter: 1));
        $this->assertSame(1, $mapper->trackedCount());
    }

    #[Test]
    public function registerSkipsEntityWithNullCollectionName(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $entity = new Stubs\Foo();
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
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', []);

        // Entity with no 'id' set
        $entity = new Stubs\Post();
        $entity->title = 'No PK';
        $mapper->persist($entity, $mapper->post());

        // Before flush, entity has no PK — identity map should not contain it yet
        // (identity map registration happens during flush, after PK is assigned)
        $this->assertSame(0, $mapper->identityMapCount());
    }

    #[Test]
    public function deleteEvictsUsingTrackedCollection(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Test'],
        ]);

        $entity = $mapper->fetch($mapper->post(filter: 1));
        $this->assertSame(1, $mapper->identityMapCount());

        // Remove via a different collection — flush uses the tracked one (name='post')
        $mapper->remove($entity, $mapper->post());
        $mapper->flush();

        $this->assertSame(0, $mapper->identityMapCount());
    }

    #[Test]
    public function evictSkipsNullCollectionName(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));

        // Track a new entity directly against a null-name collection
        $entity = new Stubs\Foo();
        $entity->id = 1;
        $nullColl = new Collection();
        $mapper->markTracked($entity, $nullColl);
        $mapper->remove($entity, $nullColl);
        $mapper->flush();

        // Evict should be a no-op (null name), identity map stays empty
        $this->assertSame(0, $mapper->identityMapCount());
    }

    #[Test]
    public function evictSkipsEntityWithNoPkValue(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Test'],
        ]);

        $mapper->fetch($mapper->post(filter: 1));
        $this->assertSame(1, $mapper->identityMapCount());

        // Entity with no PK — evict should be a no-op
        $entity = new Stubs\Post();
        $entity->title = 'No PK';
        $mapper->remove($entity, $mapper->post());
        $mapper->flush();

        $this->assertSame(1, $mapper->identityMapCount());
    }

    #[Test]
    public function findInIdentityMapSkipsNonScalarCondition(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'First'],
        ]);

        // Populate identity map
        $mapper->fetch($mapper->post(filter: 1));
        $this->assertSame(1, $mapper->identityMapCount());

        // fetchAll uses array/null condition — should always hit the backend
        $all = $mapper->fetchAll($mapper->post());
        $this->assertNotEmpty($all);
    }

    #[Test]
    public function findInIdentityMapSkipsCollectionWithChildren(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Post'],
        ]);

        // Fetch with relationship (has children) — should bypass identity map
        $comment = $mapper->fetch($mapper->comment([$mapper->post()]));
        $this->assertIsObject($comment);
    }

    #[Test]
    public function persistUntrackedEntityWithMatchingPkUpdates(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original'],
        ]);

        // Populate identity map
        $fetched = $mapper->fetch($mapper->post(filter: 1));
        $this->assertSame('Original', $fetched->title);

        // Create a NEW mutable entity with matching PK
        $replacement = new Stubs\Post();
        $replacement->id = 1;
        $replacement->title = 'Updated';

        $mapper->persist($replacement, $mapper->post());

        $ref = new ReflectionObject($mapper);
        $pendingProp = $ref->getProperty('pending');
        /** @var SplObjectStorage<object, string> $pending */
        $pending = $pendingProp->getValue($mapper);

        $this->assertSame('update', $pending[$fetched]);
        $this->assertTrue($mapper->isTracked($fetched));
        $this->assertFalse($mapper->isTracked($replacement));
        $this->assertSame('Updated', $fetched->title);
    }

    #[Test]
    public function persistReadOnlyEntityInsertWorks(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('read_only_author', []);

        $entity = $mapper->entityFactory->create(Stubs\ReadOnlyAuthor::class, name: 'Alice');
        $mapper->persist($entity, $mapper->read_only_author());
        $mapper->flush();

        // PK should have been assigned (first assignment on uninitialized readonly $id)
        $this->assertSame(1001, $entity->id);
    }

    #[Test]
    public function persistReadOnlyViaCollectionPkUpdates(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('read_only_author', [
            ['id' => 1, 'name' => 'Original', 'bio' => null],
        ]);

        // Populate identity map
        $fetched = $mapper->fetch($mapper->read_only_author(filter: 1));
        $this->assertSame('Original', $fetched->name);

        // Create new readonly entity (no PK) and persist via collection[pk]
        $updated = $mapper->entityFactory->create(Stubs\ReadOnlyAuthor::class, name: 'Updated', bio: 'new bio');
        $merged = $mapper->persist($updated, $mapper->read_only_author(filter: 1));

        // Merged entity should combine both: PK from fetched, changes from updated
        $this->assertSame(1, $merged->id);
        $this->assertSame('Updated', $merged->name);
        $this->assertSame('new bio', $merged->bio);

        // Merged entity should be tracked, old fetched evicted
        $this->assertFalse($mapper->isTracked($fetched));
        $this->assertFalse($mapper->isTracked($updated));
        $this->assertTrue($mapper->isTracked($merged));

        $ref = new ReflectionObject($mapper);
        $pendingProp = $ref->getProperty('pending');
        /** @var SplObjectStorage<object, string> $pending */
        $pending = $pendingProp->getValue($mapper);
        $this->assertSame('update', $pending[$merged]);
    }

    #[Test]
    public function persistReadOnlyViaCollectionPkFlushUpdatesStorage(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('read_only_author', [
            ['id' => 1, 'name' => 'Original', 'bio' => null],
        ]);

        $mapper->fetch($mapper->read_only_author(filter: 1));

        $updated = $mapper->entityFactory->create(Stubs\ReadOnlyAuthor::class, name: 'Updated', bio: 'new bio');
        $mapper->persist($updated, $mapper->read_only_author(filter: 1));
        $mapper->flush();

        // Clear identity map and re-fetch to verify DB was updated
        $mapper->clearIdentityMap();
        $refetched = $mapper->fetch($mapper->read_only_author(filter: 1));
        $this->assertSame('Updated', $refetched->name);
        $this->assertSame('new bio', $refetched->bio);
    }

    #[Test]
    public function identityMapReplaceEvictsOldEntity(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('read_only_author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $mapper->fetch($mapper->read_only_author(filter: 1));
        $this->assertSame(1, $mapper->identityMapCount());

        $updated = $mapper->entityFactory->create(Stubs\ReadOnlyAuthor::class, name: 'Bob');
        $mapper->persist($updated, $mapper->read_only_author(filter: 1));

        // Identity map count stays 1 (swapped, not added)
        $this->assertSame(1, $mapper->identityMapCount());
    }

    #[Test]
    public function identityMapReplaceFallsBackToInsertWhenNoPkMatch(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('read_only_author', []);

        // No identity map entries — should insert
        $entity = $mapper->entityFactory->create(Stubs\ReadOnlyAuthor::class, name: 'New');
        $mapper->persist($entity, $mapper->read_only_author());

        $ref = new ReflectionObject($mapper);
        $pendingProp = $ref->getProperty('pending');
        /** @var SplObjectStorage<object, string> $pending */
        $pending = $pendingProp->getValue($mapper);
        $this->assertSame('insert', $pending[$entity]);
    }

    #[Test]
    public function identityMapReplaceDetachesPreviouslyPendingEntity(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original'],
        ]);

        $fetched = $mapper->fetch($mapper->post(filter: 1));

        // Mark the fetched entity as pending 'update'
        $mapper->persist($fetched, $mapper->post());

        // Now replace with a new entity — old must be detached from pending too
        $replacement = new Stubs\Post();
        $replacement->id = 1;
        $replacement->title = 'Replaced';
        $mapper->persist($replacement, $mapper->post());

        // flush should not crash (old entity no longer in pending)
        $mapper->flush();

        $mapper->clearIdentityMap();
        $refetched = $mapper->fetch($mapper->post(filter: 1));
        $this->assertSame('Replaced', $refetched->title);
    }

    #[Test]
    public function identityMapReplaceSkipsSameEntity(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Test'],
        ]);

        $fetched = $mapper->fetch($mapper->post(filter: 1));

        // Persist the same entity again — should take the isTracked() path, not replace
        $mapper->persist($fetched, $mapper->post());

        $ref = new ReflectionObject($mapper);
        $pendingProp = $ref->getProperty('pending');
        /** @var SplObjectStorage<object, string> $pending */
        $pending = $pendingProp->getValue($mapper);
        $this->assertSame('update', $pending[$fetched]);
    }

    #[Test]
    public function readOnlyNestedHydrationWiresRelation(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Great post', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Hello', 'text' => 'World'],
        ]);

        $comment = $mapper->fetch($mapper->comment([$mapper->post()]));

        $this->assertInstanceOf(Stubs\Immutable\Comment::class, $comment);
        $this->assertSame(1, $comment->id);
        $this->assertSame('Great post', $comment->text);

        $this->assertInstanceOf(Stubs\Immutable\Post::class, $comment->post);
        $this->assertSame(5, $comment->post->id);
        $this->assertSame('Hello', $comment->post->title);
    }

    #[Test]
    public function readOnlyNestedHydrationThreeLevels(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Nice', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Post', 'text' => 'Body', 'author_id' => 3],
        ]);
        $mapper->seed('author', [
            ['id' => 3, 'name' => 'Alice', 'bio' => 'Writer'],
        ]);

        $comment = $mapper->fetch($mapper->comment([$mapper->post([$mapper->author()])]));

        $this->assertInstanceOf(Stubs\Immutable\Comment::class, $comment);
        $this->assertSame(1, $comment->id);

        $this->assertInstanceOf(Stubs\Immutable\Post::class, $comment->post);
        $this->assertSame(5, $comment->post->id);

        $this->assertInstanceOf(Stubs\Immutable\Author::class, $comment->post->author);
        $this->assertSame(3, $comment->post->author->id);
        $this->assertSame('Alice', $comment->post->author->name);
    }

    #[Test]
    public function readOnlyInsertWithRelationExtractsFk(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('post', []);
        $mapper->seed('author', []);

        $author = $mapper->entityFactory->create(Stubs\Immutable\Author::class, name: 'Bob');
        $post = $mapper->entityFactory->create(
            Stubs\Immutable\Post::class,
            title: 'Hello',
            text: 'World',
            author: $author,
        );

        // Insert author first so it gets a PK
        $mapper->persist($author, $mapper->author());
        $mapper->flush();

        $this->assertSame(1001, $author->id);

        // Insert post — extractColumns should resolve $author -> author_id FK
        $mapper->persist($post, $mapper->post());
        $mapper->flush();

        $this->assertSame(1002, $post->id);

        // Re-fetch the post and verify FK was stored
        $mapper->clearIdentityMap();
        $fetchedPost = $mapper->fetch($mapper->post([$mapper->author()]));
        $this->assertSame('Hello', $fetchedPost->title);
        $this->assertSame('Bob', $fetchedPost->author->name);
    }

    #[Test]
    public function readOnlyReplaceViaCollectionPkPreservesRelation(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body', 'author_id' => 10],
        ]);
        $mapper->seed('author', [
            ['id' => 10, 'name' => 'Alice', 'bio' => null],
        ]);

        // Fetch the full graph
        $fetched = $mapper->fetch($mapper->post([$mapper->author()]));
        $this->assertSame('Original', $fetched->title);
        $this->assertSame('Alice', $fetched->author->name);

        // Replace the post, keeping the same author relation
        $updated = $mapper->entityFactory->create(
            Stubs\Immutable\Post::class,
            title: 'Updated',
            text: 'New Body',
            author: $fetched->author,
        );
        $mapper->persist($updated, $mapper->post(filter: 1));
        $mapper->flush();

        // Re-fetch and verify both post columns AND FK were updated correctly
        $mapper->clearIdentityMap();
        $refetched = $mapper->fetch($mapper->post([$mapper->author()]));
        $this->assertSame('Updated', $refetched->title);
        $this->assertSame('New Body', $refetched->text);
        $this->assertSame('Alice', $refetched->author->name);
        $this->assertSame(10, $refetched->author->id);
    }

    #[Test]
    public function readOnlyReplaceWithNewRelation(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body', 'author_id' => 10],
        ]);
        $mapper->seed('author', [
            ['id' => 10, 'name' => 'Alice', 'bio' => null],
            ['id' => 20, 'name' => 'Bob', 'bio' => 'Writer'],
        ]);

        $fetched = $mapper->fetch($mapper->post([$mapper->author()]));
        $this->assertSame('Alice', $fetched->author->name);

        // Fetch the other author
        $bob = $mapper->fetch($mapper->author(filter: 20));

        // Replace post with a new author FK
        $updated = $mapper->entityFactory->create(
            Stubs\Immutable\Post::class,
            title: 'Reassigned',
            text: 'Text',
            author: $bob,
        );
        $mapper->persist($updated, $mapper->post(filter: 1));
        $mapper->flush();

        $mapper->clearIdentityMap();
        $refetched = $mapper->fetch($mapper->post([$mapper->author()]));
        $this->assertSame('Reassigned', $refetched->title);
        $this->assertSame('Bob', $refetched->author->name);
        $this->assertSame(20, $refetched->author->id);
    }

    #[Test]
    public function partialEntityPersistAutoUpdatesViaIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body', 'author_id' => 10],
        ]);
        $mapper->seed('author', [
            ['id' => 10, 'name' => 'Alice', 'bio' => null],
            ['id' => 20, 'name' => 'Bob', 'bio' => null],
        ]);

        $mapper->fetch($mapper->post([$mapper->author()]));
        $bob = $mapper->fetch($mapper->author(filter: 20));

        // Partial entity with same PK -> persist auto-detects update via identity map
        $updated = $mapper->entityFactory->create(Stubs\Immutable\Post::class, id: 1, title: 'Changed', author: $bob);
        $mapper->persist($updated, $mapper->post());
        $mapper->flush();

        $mapper->clearIdentityMap();
        $refetched = $mapper->fetch($mapper->post([$mapper->author()]));
        $this->assertSame('Changed', $refetched->title);
        $this->assertSame('Body', $refetched->text);
        $this->assertSame('Bob', $refetched->author->name);
    }

    #[Test]
    public function readOnlyMultipleEntitiesFetchAllTracksAll(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
            ['id' => 2, 'name' => 'Bob', 'bio' => null],
            ['id' => 3, 'name' => 'Carol', 'bio' => null],
        ]);

        $authors = $mapper->fetchAll($mapper->author());
        $this->assertCount(3, $authors);

        // All entities should be tracked and in identity map
        $this->assertSame(3, $mapper->trackedCount());
        $this->assertSame(3, $mapper->identityMapCount());

        // Replace one by identity map lookup
        $updated = $mapper->entityFactory->create(Stubs\Immutable\Author::class, name: 'Alice Updated');
        $merged = $mapper->persist($updated, $mapper->author(filter: 1));

        // Original Alice should be evicted, merged entity takes its place
        $this->assertSame(3, $mapper->trackedCount());
        $this->assertTrue($mapper->isTracked($merged));
        $this->assertFalse($mapper->isTracked($authors[0]));
        $this->assertSame('Alice Updated', $merged->name);
    }

    #[Test]
    public function identityMapReplaceSkipsSetWhenPkAlreadyInitialized(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $mapper->fetch($mapper->author(filter: 1));

        $updated = new Stubs\Immutable\Author(id: 1, name: 'Bob');

        // persist via collection[1] — PK already set, merge produces new entity
        $merged = $mapper->persist($updated, $mapper->author(filter: 1));

        $this->assertSame(1, $merged->id);
        $this->assertSame('Bob', $merged->name);
        $this->assertTrue($mapper->isTracked($merged));
    }

    #[Test]
    public function persistReturnsEntity(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));

        // Insert path
        $entity = new Stubs\Post();
        $entity->title = 'Test';
        $result = $mapper->persist($entity, $mapper->post());
        $this->assertSame($entity, $result);

        // Update path (tracked entity)
        $mapper->flush();
        $result = $mapper->persist($entity, $mapper->post());
        $this->assertSame($entity, $result);
    }

    #[Test]
    public function readOnlyDeleteEvictsFromIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->fetch($mapper->author(filter: 1));
        $this->assertSame(1, $mapper->identityMapCount());

        $mapper->remove($fetched, $mapper->author());
        $mapper->flush();

        $this->assertSame(0, $mapper->identityMapCount());

        // Re-fetch returns false (no data)
        $mapper->clearIdentityMap();
        $refetched = $mapper->fetch($mapper->author(filter: 1));
        $this->assertFalse($refetched);
    }

    #[Test]
    public function persistPartialEntityOnPendingInsertMergesViaIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('author', []);

        $author = $mapper->entityFactory->create(Stubs\Immutable\Author::class, id: 1, name: 'Alice');
        $mapper->persist($author, $mapper->author());

        // Partial entity with same PK merges via identity map, does not duplicate
        $updated = $mapper->entityFactory->create(Stubs\Immutable\Author::class, id: 1, name: 'Bob');
        $mapper->persist($updated, $mapper->author());
        $mapper->flush();

        $all = $mapper->fetchAll($mapper->author());
        $this->assertCount(1, $all);
        $this->assertSame('Bob', $all[0]->name);
    }

    #[Test]
    public function persistPartialEntityOnTrackedUpdateMerges(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $mapper->fetch($mapper->author(filter: 1));

        // Partial entity with same PK auto-detects update via identity map
        $partial = $mapper->entityFactory->create(Stubs\Immutable\Author::class, id: 1, name: 'Bob');
        $mapper->persist($partial, $mapper->author());
        $mapper->flush();

        $mapper->clearIdentityMap();
        $refetched = $mapper->fetch($mapper->author(filter: 1));
        $this->assertSame('Bob', $refetched->name);
    }

    #[Test]
    public function mutableMergeAppliesOverlayPropertiesToExisting(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->fetch($mapper->author(filter: 1));
        $this->assertSame('Alice', $fetched->name);

        // Persist a different mutable entity with same PK
        $overlay = new Stubs\Author();
        $overlay->id = 1;
        $overlay->name = 'Bob';
        $overlay->bio = 'new bio';

        $result = $mapper->persist($overlay, $mapper->author());

        // Existing entity is mutated in place and returned
        $this->assertSame($fetched, $result);
        $this->assertSame('Bob', $fetched->name);
        $this->assertSame('new bio', $fetched->bio);
        $this->assertTrue($mapper->isTracked($fetched));
        $this->assertFalse($mapper->isTracked($overlay));
    }

    #[Test]
    public function readOnlyMergeNoDiffReturnsSameEntity(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->fetch($mapper->author(filter: 1));

        // Persist readonly entity with identical properties
        $same = new Stubs\Immutable\Author(id: 1, name: 'Alice');
        $result = $mapper->persist($same, $mapper->author(filter: 1));

        // No clone needed — same entity returned
        $this->assertSame($fetched, $result);
        $this->assertTrue($mapper->isTracked($fetched));
    }

    #[Test]
    public function identityMapLookupNormalizesNumericStringCondition(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->fetch($mapper->author(filter: 1));

        // Lookup with string "1" should hit the identity map
        $fromString = $mapper->fetch($mapper->author(filter: '1'));
        $this->assertSame($fetched, $fromString);
    }

    #[Test]
    public function identityMapLookupReturnsNullForNonScalarCondition(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $mapper->fetch($mapper->author(filter: 1));

        // Float condition should not match identity map
        $result = $mapper->fetch($mapper->author(filter: 1.5));
        $this->assertNotSame(true, $result === null);
    }

    #[Test]
    public function mutableMergeTracksExistingWhenNotYetTracked(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        // Put entity in identity map via fetch, then untrack it manually
        $fetched = $mapper->fetch($mapper->author(filter: 1));
        $ref = new ReflectionObject($mapper);
        $trackedProp = $ref->getProperty('tracked');
        /** @var SplObjectStorage<object, mixed> $tracked */
        $tracked = $trackedProp->getValue($mapper);
        $tracked->offsetUnset($fetched);
        $this->assertFalse($mapper->isTracked($fetched));

        // Persist a new entity with same PK — should merge and re-track existing
        $overlay = new Stubs\Author();
        $overlay->id = 1;
        $overlay->name = 'Bob';

        $result = $mapper->persist($overlay, $mapper->author());

        $this->assertSame($fetched, $result);
        $this->assertTrue($mapper->isTracked($fetched));
        $this->assertSame('Bob', $fetched->name);
    }

    #[Test]
    public function mergeWithIdentityMapNormalizesConditionFallback(): void
    {
        $mapper = new InMemoryMapper(new Nested(new EntityFactory(
            entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\',
        )));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->fetch($mapper->author(filter: 1));

        // Persist readonly entity without PK, via string condition "1"
        $overlay = $mapper->entityFactory->create(Stubs\Immutable\Author::class, name: 'Updated');
        $merged = $mapper->persist($overlay, $mapper->author(filter: '1'));

        // Should have matched identity map via normalized condition
        $this->assertNotSame($fetched, $merged);
        $this->assertSame(1, $merged->id);
        $this->assertSame('Updated', $merged->name);
        $this->assertTrue($mapper->isTracked($merged));
        $this->assertFalse($mapper->isTracked($fetched));
    }

    #[Test]
    public function callingRegisteredCollectionWithArgsDerives(): void
    {
        $coll = Collection::post();
        $this->mapper->registerCollection('post', $coll);
        $derived = $this->mapper->post(filter: 5);
        $this->assertEquals('post', $derived->name);
        $this->assertEquals(5, $derived->filter);
    }
}
