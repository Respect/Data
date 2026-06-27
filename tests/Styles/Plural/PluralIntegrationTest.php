<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Plural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrators\Nested;
use Respect\Data\InMemoryMapper;
use Respect\Data\Styles\Plural;

use function is_object;

#[CoversClass(Plural::class)]
class PluralIntegrationTest extends TestCase
{
    private Plural $style;

    private InMemoryMapper $mapper;

    protected function setUp(): void
    {
        $this->style = new Plural();
        $this->mapper = new InMemoryMapper(new Nested(new EntityFactory(
            style: $this->style,
            entityNamespace: __NAMESPACE__ . '\\',
        )));

        $this->mapper->seed('posts', [
            ['id' => 5, 'title' => 'Post Title', 'text' => 'Post Text', 'author_id' => 1],
        ]);
        $this->mapper->seed('authors', [
            ['id' => 1, 'name' => 'Author 1'],
        ]);
        $this->mapper->seed('comments', [
            ['id' => 7, 'post_id' => 5, 'text' => 'Comment Text'],
            ['id' => 8, 'post_id' => 4, 'text' => 'Comment Text 2'],
        ]);
        $this->mapper->seed('categories', [
            ['id' => 2, 'name' => 'Sample Category'],
            ['id' => 3, 'name' => 'NONON'],
        ]);
        $this->mapper->seed('posts_categories', [
            ['id' => 66, 'post_id' => 5, 'category_id' => 2],
        ]);
    }

    #[Test]
    public function fetchSingularScopeResolvesToPluralTable(): void
    {
        // Scope name is PHP-conventional singular `post`; the Plural style
        // resolves it to the `posts` seed table via realName().
        $entity = $this->mapper->fetch($this->mapper->post());
        $this->assertIsObject($entity);
        $this->assertEquals('Post Title', $this->mapper->entityFactory->get($entity, 'title'));
    }

    #[Test]
    public function fetchNestedRelationResolvesEachTable(): void
    {
        // post (→ posts) nesting author (→ authors): realName() drives both
        // table lookups while the scope names stay singular.
        $post = $this->mapper->fetch($this->mapper->post([$this->mapper->author()]));
        $this->assertIsObject($post);

        $author = $this->mapper->entityFactory->get($post, 'author');
        $this->assertTrue(is_object($author));
        $this->assertEquals('Author 1', $this->mapper->entityFactory->get($author, 'name'));
    }
}
