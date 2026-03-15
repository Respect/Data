<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Sakila;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Respect\Data\InMemoryMapper;
use Respect\Data\Styles\Sakila;

#[CoversClass(InMemoryMapper::class)]
#[CoversClass(Sakila::class)]
class SakilaIntegrationTest extends TestCase
{
    private Sakila $style;

    private InMemoryMapper $mapper;

    protected function setUp(): void
    {
        $this->style = new Sakila();
        $this->mapper = new InMemoryMapper();
        $this->mapper->setStyle($this->style);
        $this->mapper->entityNamespace = __NAMESPACE__ . '\\';

        $this->mapper->seed('post', [
            ['post_id' => 5, 'title' => 'Post Title', 'text' => 'Post Text', 'author_id' => 1],
        ]);
        $this->mapper->seed('author', [
            ['author_id' => 1, 'name' => 'Author 1'],
        ]);
        $this->mapper->seed('comment', [
            ['comment_id' => 7, 'post_id' => 5, 'text' => 'Comment Text'],
            ['comment_id' => 8, 'post_id' => 4, 'text' => 'Comment Text 2'],
        ]);
        $this->mapper->seed('category', [
            ['category_id' => 2, 'name' => 'Sample Category', 'content' => null],
            ['category_id' => 3, 'name' => 'NONON', 'content' => null],
        ]);
        $this->mapper->seed('post_category', [
            ['post_category_id' => 66, 'post_id' => 5, 'category_id' => 2],
        ]);
    }

    public function testFetchingEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment[8]->fetch();
        $this->assertInstanceOf(Comment::class, $comment);
    }

    public function testFetchingAllEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment->fetchAll();
        $this->assertInstanceOf(Comment::class, $comment[1]);

        $categories = $mapper->post_category->category->fetch();
        $this->assertInstanceOf(PostCategory::class, $categories);
        $this->assertInstanceOf(Category::class, $categories->category_id);
    }

    public function testFetchingAllEntityTypedNested(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment->post->author->fetchAll();
        $this->assertInstanceOf(Comment::class, $comment[0]);
        $this->assertInstanceOf(Post::class, $comment[0]->post_id);
        $this->assertInstanceOf(Author::class, $comment[0]->post_id->author_id);
    }

    public function testPersistingEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->comment[8]->fetch();
        $this->assertInstanceOf(Comment::class, $comment);
        $comment->text = 'HeyHey';
        $mapper->comment->persist($comment);
        $mapper->flush();

        $updated = $mapper->comment[8]->fetch();
        $this->assertInstanceOf(Comment::class, $updated);
        $this->assertEquals('HeyHey', $updated->text);
    }

    public function testPersistingNewEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = new Comment();
        $comment->text = 'HeyHey';
        $mapper->comment->persist($comment);
        $mapper->flush();

        $this->assertNotNull($comment->comment_id);
        $allComments = $mapper->comment->fetchAll();
        $this->assertCount(3, $allComments);
    }
}
