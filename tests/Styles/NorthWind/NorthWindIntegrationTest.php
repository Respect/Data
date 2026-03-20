<?php

declare(strict_types=1);

namespace Respect\Data\Styles\NorthWind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Respect\Data\EntityFactory;
use Respect\Data\InMemoryMapper;
use Respect\Data\Styles\NorthWind;

#[CoversClass(NorthWind::class)]
class NorthWindIntegrationTest extends TestCase
{
    private NorthWind $style;

    private InMemoryMapper $mapper;

    protected function setUp(): void
    {
        $this->style = new NorthWind();
        $this->mapper = new InMemoryMapper(new EntityFactory(
            style: $this->style,
            entityNamespace: __NAMESPACE__ . '\\',
        ));

        $this->mapper->seed('Posts', [
            ['PostID' => 5, 'Title' => 'Post Title', 'Text' => 'Post Text', 'AuthorID' => 1],
        ]);
        $this->mapper->seed('Authors', [
            ['AuthorID' => 1, 'Name' => 'Author 1'],
        ]);
        $this->mapper->seed('Comments', [
            ['CommentID' => 7, 'PostID' => 5, 'Text' => 'Comment Text'],
            ['CommentID' => 8, 'PostID' => 4, 'Text' => 'Comment Text 2'],
        ]);
        $this->mapper->seed('Categories', [
            ['CategoryID' => 2, 'Name' => 'Sample Category', 'Description' => 'Category description'],
            ['CategoryID' => 3, 'Name' => 'NONON', 'Description' => null],
        ]);
        $this->mapper->seed('PostCategories', [
            ['PostCategoryID' => 66, 'PostID' => 5, 'CategoryID' => 2],
        ]);
    }

    public function testFetchingEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->Comments[8]->fetch();
        $this->assertInstanceOf(Comments::class, $comment);
    }

    public function testFetchingAllEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->Comments->fetchAll();
        $this->assertInstanceOf(Comments::class, $comment[1]);

        $categories = $mapper->PostCategories->Categories->fetch();
        $this->assertInstanceOf(PostCategories::class, $categories);
        $this->assertInstanceOf(Categories::class, $categories->Category);
    }

    public function testFetchingAllEntityTypedNested(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->Comments->Posts->Authors->fetchAll();
        $this->assertInstanceOf(Comments::class, $comment[0]);
        $this->assertInstanceOf(Posts::class, $comment[0]->Post);
        $this->assertInstanceOf(Authors::class, $comment[0]->Post->Author);
    }

    public function testPersistingEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = $mapper->Comments[8]->fetch();
        $this->assertInstanceOf(Comments::class, $comment);
        $comment->Text = 'HeyHey';
        $mapper->Comments->persist($comment);
        $mapper->flush();

        $updated = $mapper->Comments[8]->fetch();
        $this->assertInstanceOf(Comments::class, $updated);
        $this->assertEquals('HeyHey', $updated->Text);
    }

    public function testPersistingNewEntityTyped(): void
    {
        $mapper = $this->mapper;
        $comment = new Comments();
        $comment->Text = 'HeyHey';
        $mapper->Comments->persist($comment);
        $mapper->flush();

        $this->assertNotNull($comment->CommentID);
        $allComments = $mapper->Comments->fetchAll();
        $this->assertCount(3, $allComments);
    }
}
