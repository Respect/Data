<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Plural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrators\Nested;
use Respect\Data\InMemoryMapper;
use Respect\Data\Styles\Plural;

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

    public function testFetchingEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->fetch($mapper->comments(filter: 8));
        $this->assertInstanceOf(Comment::class, $comment);
    }

    public function testFetchingAllEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->fetchAll($mapper->comments());
        $this->assertInstanceOf(Comment::class, $comment[1]);

        $categories = $mapper->fetch($mapper->posts_categories([$mapper->categories()]));
        $this->assertInstanceOf(PostCategory::class, $categories);
        $this->assertInstanceOf(Category::class, $categories->category);
    }

    public function testFetchingAllEntityTypedNested(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->fetchAll($mapper->comments([$mapper->posts([$mapper->authors()])]));
        $this->assertInstanceOf(Comment::class, $comment[0]);
        $this->assertInstanceOf(Post::class, $comment[0]->post);
        $this->assertInstanceOf(Author::class, $comment[0]->post->author);
    }

    public function testPersistingEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->fetch($mapper->comments(filter: 8));
        $this->assertInstanceOf(Comment::class, $comment);
        $comment->text = 'HeyHey';
        $mapper->persist($comment, $mapper->comments());
        $mapper->flush();

        $updated = $mapper->fetch($mapper->comments(filter: 8));
        $this->assertInstanceOf(Comment::class, $updated);
        $this->assertEquals('HeyHey', $updated->text);
    }

    public function testPersistingNewEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = new Comment();
        $comment->text = 'HeyHey';
        $mapper->persist($comment, $mapper->comments());
        $mapper->flush();

        $this->assertGreaterThan(0, $comment->id);
        $allComments = $mapper->fetchAll($mapper->comments());
        $this->assertCount(3, $allComments);
    }
}
