<?php

declare(strict_types=1);

namespace Respect\Data\Styles\CakePHP;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Respect\Data\EntityFactory;
use Respect\Data\InMemoryMapper;
use Respect\Data\Styles\CakePHP;

#[CoversClass(CakePHP::class)]
class CakePHPIntegrationTest extends TestCase
{
    private CakePHP $style;

    private InMemoryMapper $mapper;

    protected function setUp(): void
    {
        $this->style = new CakePHP();
        $this->mapper = new InMemoryMapper(new EntityFactory(
            style: $this->style,
            entityNamespace: __NAMESPACE__ . '\\',
        ));

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
            ['id' => 2, 'name' => 'Sample Category', 'category_id' => null],
            ['id' => 3, 'name' => 'NONON', 'category_id' => null],
        ]);
        $this->mapper->seed('post_categories', [
            ['id' => 66, 'post_id' => 5, 'category_id' => 2],
        ]);
    }

    public function testFetchingEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comments[8]->fetch();
        $this->assertInstanceOf(Comment::class, $comment);
    }

    public function testFetchingAllEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comments->fetchAll();
        $this->assertInstanceOf(Comment::class, $comment[1]);

        $categories = $mapper->post_categories->categories->fetch();
        $this->assertInstanceOf(PostCategory::class, $categories);
        $this->assertInstanceOf(Category::class, $categories->category_id);
    }

    public function testFetchingAllEntityTypedNested(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comments->posts->authors->fetchAll();
        $this->assertInstanceOf(Comment::class, $comment[0]);
        $this->assertInstanceOf(Post::class, $comment[0]->post_id);
        $this->assertInstanceOf(Author::class, $comment[0]->post_id->author_id);
    }

    public function testPersistingEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comments[8]->fetch();
        $this->assertInstanceOf(Comment::class, $comment);
        $comment->text = 'HeyHey';
        $mapper->comments->persist($comment);
        $mapper->flush();

        $updated = $mapper->comments[8]->fetch();
        $this->assertInstanceOf(Comment::class, $updated);
        $this->assertEquals('HeyHey', $updated->text);
    }

    public function testPersistingNewEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = new Comment();
        $comment->text = 'HeyHey';
        $mapper->comments->persist($comment);
        $mapper->flush();

        $this->assertNotNull($comment->id);
        $allComments = $mapper->comments->fetchAll();
        $this->assertCount(3, $allComments);
    }
}
