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

#[CoversClass(AbstractMapper::class)]
class AbstractMapperTest extends TestCase
{
    protected AbstractMapper $mapper;

    protected function setUp(): void
    {
        $factory = new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\');
        $this->mapper = new class ($factory) extends AbstractMapper {
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
        $collection = $this->mapper->author()->post()->comment();
        $this->assertEquals('author', $collection->name);
        $this->assertEquals('post', $collection->next?->name);
        $this->assertEquals('comment', $collection->next?->next?->name);
    }

    #[Test]
    public function magicGetterShouldBypassToCollection(): void
    {
        $collection = $this->mapper->author->post->comment;
        $this->assertEquals('author', $collection->name);
        $this->assertEquals('post', $collection->next?->name);
        $this->assertEquals('comment', $collection->next?->next?->name);
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
    public function magicGetShouldReturnNewCollectionWhenNotRegistered(): void
    {
        $coll = $this->mapper->author;
        $this->assertInstanceOf(Collection::class, $coll);
        $this->assertEquals('author', $coll->name);
    }

    #[Test]
    public function hydrationWiresRelatedEntity(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Post'],
        ]);

        $comment = $mapper->comment->post->fetch();
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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
    }

    #[Test]
    public function hydrationWithNoMatchLeavesRelationNull(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 999],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Post'],
        ]);

        $comment = $mapper->comment->post->fetch();
        $this->assertIsObject($comment);
        // No post with id=999 exists, so relation stays null
        $this->assertNull($mapper->entityFactory->get($comment, 'post'));
    }

    #[Test]
    public function hydrationWiresRelationWithStringPk(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Hello', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => '5', 'title' => 'Post'],
        ]);

        $comment = $mapper->comment->post->fetch();
        $this->assertIsObject($comment);
        $post = $mapper->entityFactory->get($comment, 'post');
        $this->assertIsObject($post);
        $this->assertEquals('5', $mapper->entityFactory->get($post, 'id'));
    }

    #[Test]
    public function callingRegisteredCollectionClonesAndAppliesCondition(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Hello'],
            ['id' => 2, 'title' => 'World'],
        ]);

        $coll = Filtered::posts('title');
        $mapper->postTitles = $coll;

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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $coll = Filtered::posts('title');
        $mapper->postTitles = $coll;

        $clone = $mapper->postTitles();

        $this->assertInstanceOf(Filtered::class, $clone);
        $this->assertNotSame($mapper->postTitles, $clone);
        $this->assertEquals('posts', $clone->name);
        $this->assertEquals(['title'], $clone->filters);
    }

    #[Test]
    public function callingRegisteredChainedCollectionDoesNotMutateTemplate(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', []);
        $mapper->seed('comment', []);

        $coll = Collection::posts();
        $mapper->commentedPosts = $coll->comment();

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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', []);
        $mapper->seed('author', []);
        $mapper->authorsWithPosts = Filtered::post()->author();

        $author = new Stubs\Author();
        $author->name = 'Test';
        $mapper->authorsWithPosts->persist($author);
        $mapper->flush();

        $fetched = $mapper->author->fetch();
        $this->assertEquals('Test', $fetched->name);
    }

    #[Test]
    public function filteredWithoutNextFallsBackToNormalPersist(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', []);

        $post = new Stubs\Post();
        $post->title = 'Direct';
        $mapper->post->persist($post);
        $mapper->flush();

        $fetched = $mapper->post->fetch();
        $this->assertEquals('Direct', $fetched->title);
    }

    #[Test]
    public function filteredUpdatePersistsOnlyFilteredColumns(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body'],
        ]);

        $postTitles = Filtered::post('title');
        $mapper->postTitles = $postTitles;
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', []);

        $postTitles = Filtered::post('title');
        $mapper->postTitles = $postTitles;
        $post = new Stubs\Post();
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body'],
        ]);

        $allPosts = Filtered::post();
        $mapper->allPosts = $allPosts;
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body'],
        ]);

        $postIds = Filtered::post(Filtered::IDENTIFIER_ONLY);
        $mapper->postIds = $postIds;
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', []);

        $entity = new Stubs\Post();
        $entity->title = 'New Post';
        $mapper->post->persist($entity);
        $mapper->flush();

        $this->assertSame(1, $mapper->identityMapCount());
    }

    #[Test]
    public function flushDeleteEvictsFromIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Existing'],
        ]);

        $ref = new ReflectionObject($mapper);
        $pendingProp = $ref->getProperty('pending');

        // persist new entity → 'insert'
        $newEntity = new Stubs\Post();
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', []);

        // Entity with no 'id' set
        $entity = new Stubs\Post();
        $entity->title = 'No PK';
        $mapper->post->persist($entity);

        // Before flush, entity has no PK — identity map should not contain it yet
        // (identity map registration happens during flush, after PK is assigned)
        $this->assertSame(0, $mapper->identityMapCount());
    }

    #[Test]
    public function deleteEvictsUsingTrackedCollection(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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
    public function findInIdentityMapSkipsCollectionWithChildren(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
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

    #[Test]
    public function persistUntrackedEntityWithMatchingPkUpdates(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original'],
        ]);

        // Populate identity map
        $fetched = $mapper->post[1]->fetch();
        $this->assertSame('Original', $fetched->title);

        // Create a NEW mutable entity with matching PK
        $replacement = new Stubs\Post();
        $replacement->id = 1;
        $replacement->title = 'Updated';

        $mapper->post->persist($replacement);

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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('read_only_author', []);

        $entity = $mapper->entityFactory->create(Stubs\ReadOnlyAuthor::class, name: 'Alice');
        $mapper->read_only_author->persist($entity);
        $mapper->flush();

        // PK should have been assigned (first assignment on uninitialized readonly $id)
        $this->assertSame(1001, $entity->id);
    }

    #[Test]
    public function persistReadOnlyViaCollectionPkUpdates(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('read_only_author', [
            ['id' => 1, 'name' => 'Original', 'bio' => null],
        ]);

        // Populate identity map
        $fetched = $mapper->read_only_author[1]->fetch();
        $this->assertSame('Original', $fetched->name);

        // Create new readonly entity (no PK) and persist via collection[pk]
        $updated = $mapper->entityFactory->create(Stubs\ReadOnlyAuthor::class, name: 'Updated', bio: 'new bio');
        $merged = $mapper->read_only_author[1]->persist($updated);

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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('read_only_author', [
            ['id' => 1, 'name' => 'Original', 'bio' => null],
        ]);

        $mapper->read_only_author[1]->fetch();

        $updated = $mapper->entityFactory->create(Stubs\ReadOnlyAuthor::class, name: 'Updated', bio: 'new bio');
        $mapper->read_only_author[1]->persist($updated);
        $mapper->flush();

        // Clear identity map and re-fetch to verify DB was updated
        $mapper->clearIdentityMap();
        $refetched = $mapper->read_only_author[1]->fetch();
        $this->assertSame('Updated', $refetched->name);
        $this->assertSame('new bio', $refetched->bio);
    }

    #[Test]
    public function identityMapReplaceEvictsOldEntity(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('read_only_author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $mapper->read_only_author[1]->fetch();
        $this->assertSame(1, $mapper->identityMapCount());

        $updated = $mapper->entityFactory->create(Stubs\ReadOnlyAuthor::class, name: 'Bob');
        $mapper->read_only_author[1]->persist($updated);

        // Identity map count stays 1 (swapped, not added)
        $this->assertSame(1, $mapper->identityMapCount());
    }

    #[Test]
    public function identityMapReplaceFallsBackToInsertWhenNoPkMatch(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('read_only_author', []);

        // No identity map entries — should insert
        $entity = $mapper->entityFactory->create(Stubs\ReadOnlyAuthor::class, name: 'New');
        $mapper->read_only_author->persist($entity);

        $ref = new ReflectionObject($mapper);
        $pendingProp = $ref->getProperty('pending');
        /** @var SplObjectStorage<object, string> $pending */
        $pending = $pendingProp->getValue($mapper);
        $this->assertSame('insert', $pending[$entity]);
    }

    #[Test]
    public function identityMapReplaceDetachesPreviouslyPendingEntity(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original'],
        ]);

        $fetched = $mapper->post[1]->fetch();

        // Mark the fetched entity as pending 'update'
        $mapper->post->persist($fetched);

        // Now replace with a new entity — old must be detached from pending too
        $replacement = new Stubs\Post();
        $replacement->id = 1;
        $replacement->title = 'Replaced';
        $mapper->post->persist($replacement);

        // flush should not crash (old entity no longer in pending)
        $mapper->flush();

        $mapper->clearIdentityMap();
        $refetched = $mapper->post[1]->fetch();
        $this->assertSame('Replaced', $refetched->title);
    }

    #[Test]
    public function identityMapReplaceSkipsSameEntity(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Test'],
        ]);

        $fetched = $mapper->post[1]->fetch();

        // Persist the same entity again — should take the isTracked() path, not replace
        $mapper->post->persist($fetched);

        $ref = new ReflectionObject($mapper);
        $pendingProp = $ref->getProperty('pending');
        /** @var SplObjectStorage<object, string> $pending */
        $pending = $pendingProp->getValue($mapper);
        $this->assertSame('update', $pending[$fetched]);
    }

    #[Test]
    public function readOnlyNestedHydrationWiresRelation(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Great post', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Hello', 'text' => 'World'],
        ]);

        $comment = $mapper->comment->post->fetch();

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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('comment', [
            ['id' => 1, 'text' => 'Nice', 'post_id' => 5],
        ]);
        $mapper->seed('post', [
            ['id' => 5, 'title' => 'Post', 'text' => 'Body', 'author_id' => 3],
        ]);
        $mapper->seed('author', [
            ['id' => 3, 'name' => 'Alice', 'bio' => 'Writer'],
        ]);

        $comment = $mapper->comment->post->author->fetch();

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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
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
        $mapper->author->persist($author);
        $mapper->flush();

        $this->assertSame(1001, $author->id);

        // Insert post — extractColumns should resolve $author → author_id FK
        $mapper->post->persist($post);
        $mapper->flush();

        $this->assertSame(1002, $post->id);

        // Re-fetch the post and verify FK was stored
        $mapper->clearIdentityMap();
        $fetchedPost = $mapper->post->author->fetch();
        $this->assertSame('Hello', $fetchedPost->title);
        $this->assertSame('Bob', $fetchedPost->author->name);
    }

    #[Test]
    public function readOnlyReplaceViaCollectionPkPreservesRelation(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body', 'author_id' => 10],
        ]);
        $mapper->seed('author', [
            ['id' => 10, 'name' => 'Alice', 'bio' => null],
        ]);

        // Fetch the full graph
        $fetched = $mapper->post->author->fetch();
        $this->assertSame('Original', $fetched->title);
        $this->assertSame('Alice', $fetched->author->name);

        // Replace the post, keeping the same author relation
        $updated = $mapper->entityFactory->create(
            Stubs\Immutable\Post::class,
            title: 'Updated',
            text: 'New Body',
            author: $fetched->author,
        );
        $mapper->post[1]->persist($updated);
        $mapper->flush();

        // Re-fetch and verify both post columns AND FK were updated correctly
        $mapper->clearIdentityMap();
        $refetched = $mapper->post->author->fetch();
        $this->assertSame('Updated', $refetched->title);
        $this->assertSame('New Body', $refetched->text);
        $this->assertSame('Alice', $refetched->author->name);
        $this->assertSame(10, $refetched->author->id);
    }

    #[Test]
    public function readOnlyReplaceWithNewRelation(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body', 'author_id' => 10],
        ]);
        $mapper->seed('author', [
            ['id' => 10, 'name' => 'Alice', 'bio' => null],
            ['id' => 20, 'name' => 'Bob', 'bio' => 'Writer'],
        ]);

        $fetched = $mapper->post->author->fetch();
        $this->assertSame('Alice', $fetched->author->name);

        // Fetch the other author
        $bob = $mapper->author[20]->fetch();

        // Replace post with a new author FK
        $updated = $mapper->entityFactory->create(
            Stubs\Immutable\Post::class,
            title: 'Reassigned',
            text: 'Text',
            author: $bob,
        );
        $mapper->post[1]->persist($updated);
        $mapper->flush();

        $mapper->clearIdentityMap();
        $refetched = $mapper->post->author->fetch();
        $this->assertSame('Reassigned', $refetched->title);
        $this->assertSame('Bob', $refetched->author->name);
        $this->assertSame(20, $refetched->author->id);
    }

    #[Test]
    public function withChangesAndPersistAutoUpdatesViaIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('post', [
            ['id' => 1, 'title' => 'Original', 'text' => 'Body', 'author_id' => 10],
        ]);
        $mapper->seed('author', [
            ['id' => 10, 'name' => 'Alice', 'bio' => null],
            ['id' => 20, 'name' => 'Bob', 'bio' => null],
        ]);

        $post = $mapper->post->author->fetch();
        $bob = $mapper->author[20]->fetch();

        // withChanges preserves PK → persist auto-detects update via identity map
        $updated = $mapper->entityFactory->withChanges($post, title: 'Changed', author: $bob);
        $mapper->post->persist($updated);
        $mapper->flush();

        $mapper->clearIdentityMap();
        $refetched = $mapper->post->author->fetch();
        $this->assertSame('Changed', $refetched->title);
        $this->assertSame('Body', $refetched->text);
        $this->assertSame('Bob', $refetched->author->name);
    }

    #[Test]
    public function readOnlyMultipleEntitiesFetchAllTracksAll(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
            ['id' => 2, 'name' => 'Bob', 'bio' => null],
            ['id' => 3, 'name' => 'Carol', 'bio' => null],
        ]);

        $authors = $mapper->author->fetchAll();
        $this->assertCount(3, $authors);

        // All entities should be tracked and in identity map
        $this->assertSame(3, $mapper->trackedCount());
        $this->assertSame(3, $mapper->identityMapCount());

        // Replace one by identity map lookup
        $updated = $mapper->entityFactory->create(Stubs\Immutable\Author::class, name: 'Alice Updated');
        $merged = $mapper->author[1]->persist($updated);

        // Original Alice should be evicted, merged entity takes its place
        $this->assertSame(3, $mapper->trackedCount());
        $this->assertTrue($mapper->isTracked($merged));
        $this->assertFalse($mapper->isTracked($authors[0]));
        $this->assertSame('Alice Updated', $merged->name);
    }

    #[Test]
    public function identityMapReplaceSkipsSetWhenPkAlreadyInitialized(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $mapper->author[1]->fetch();

        $updated = new Stubs\Immutable\Author(id: 1, name: 'Bob');

        // persist via collection[1] — PK already set, merge produces new entity
        $merged = $mapper->author[1]->persist($updated);

        $this->assertSame(1, $merged->id);
        $this->assertSame('Bob', $merged->name);
        $this->assertTrue($mapper->isTracked($merged));
    }

    #[Test]
    public function persistReturnsEntity(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));

        // Insert path
        $entity = new Stubs\Post();
        $entity->title = 'Test';
        $result = $mapper->post->persist($entity);
        $this->assertSame($entity, $result);

        // Update path (tracked entity)
        $mapper->flush();
        $result = $mapper->post->persist($entity);
        $this->assertSame($entity, $result);
    }

    #[Test]
    public function readOnlyDeleteEvictsFromIdentityMap(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->author[1]->fetch();
        $this->assertSame(1, $mapper->identityMapCount());

        $mapper->author->remove($fetched);
        $mapper->flush();

        $this->assertSame(0, $mapper->identityMapCount());

        // Re-fetch returns false (no data)
        $mapper->clearIdentityMap();
        $refetched = $mapper->author[1]->fetch();
        $this->assertFalse($refetched);
    }

    #[Test]
    public function persistWithChangesOnPendingInsertReplacesOriginal(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('author', []);

        $author = $mapper->entityFactory->create(Stubs\Immutable\Author::class, name: 'Alice');
        $mapper->author->persist($author);

        // Persist with changes on a pending-insert entity must replace, not duplicate
        $updated = $mapper->author->persist($author, name: 'Bob');
        $mapper->flush();

        $all = $mapper->author->fetchAll();
        $this->assertCount(1, $all);
        $this->assertSame('Bob', $all[0]->name);
        $this->assertFalse($mapper->isTracked($author));
        $this->assertTrue($mapper->isTracked($updated));
    }

    #[Test]
    public function persistWithChangesOnTrackedUpdateReplacesOriginal(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->author[1]->fetch();

        // Persist with changes on a tracked (fetched) entity
        $mapper->author->persist($fetched, name: 'Bob');
        $mapper->flush();

        $mapper->clearIdentityMap();
        $refetched = $mapper->author[1]->fetch();
        $this->assertSame('Bob', $refetched->name);
    }

    #[Test]
    public function mutableMergeAppliesOverlayPropertiesToExisting(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->author[1]->fetch();
        $this->assertSame('Alice', $fetched->name);

        // Persist a different mutable entity with same PK
        $overlay = new Stubs\Author();
        $overlay->id = 1;
        $overlay->name = 'Bob';
        $overlay->bio = 'new bio';

        $result = $mapper->author->persist($overlay);

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
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->author[1]->fetch();

        // Persist readonly entity with identical properties
        $same = new Stubs\Immutable\Author(id: 1, name: 'Alice');
        $result = $mapper->author[1]->persist($same);

        // No clone needed — same entity returned
        $this->assertSame($fetched, $result);
        $this->assertTrue($mapper->isTracked($fetched));
    }

    #[Test]
    public function identityMapLookupNormalizesNumericStringCondition(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->author[1]->fetch();

        // Lookup with string "1" should hit the identity map
        $fromString = $mapper->author['1']->fetch();
        $this->assertSame($fetched, $fromString);
    }

    #[Test]
    public function identityMapLookupReturnsNullForNonScalarCondition(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $mapper->author[1]->fetch();

        // Float condition should not match identity map
        $result = $mapper->author[1.5]->fetch();
        $this->assertNotSame(true, $result === null);
    }

    #[Test]
    public function mutableMergeTracksExistingWhenNotYetTracked(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        // Put entity in identity map via fetch, then untrack it manually
        $fetched = $mapper->author[1]->fetch();
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

        $result = $mapper->author->persist($overlay);

        $this->assertSame($fetched, $result);
        $this->assertTrue($mapper->isTracked($fetched));
        $this->assertSame('Bob', $fetched->name);
    }

    #[Test]
    public function mergeWithIdentityMapNormalizesConditionFallback(): void
    {
        $mapper = new InMemoryMapper(new EntityFactory(entityNamespace: 'Respect\\Data\\Stubs\\Immutable\\'));
        $mapper->seed('author', [
            ['id' => 1, 'name' => 'Alice', 'bio' => null],
        ]);

        $fetched = $mapper->author[1]->fetch();

        // Persist readonly entity without PK, via string condition "1"
        $overlay = $mapper->entityFactory->create(Stubs\Immutable\Author::class, name: 'Updated');
        $merged = $mapper->author['1']->persist($overlay);

        // Should have matched identity map via normalized condition
        $this->assertNotSame($fetched, $merged);
        $this->assertSame(1, $merged->id);
        $this->assertSame('Updated', $merged->name);
        $this->assertTrue($mapper->isTracked($merged));
        $this->assertFalse($mapper->isTracked($fetched));
    }
}
