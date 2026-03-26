<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Plural;

class PostCategory
{
    public int $id;

    public Post $post;

    public Category $category;
}
