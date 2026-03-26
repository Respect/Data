<?php

declare(strict_types=1);

namespace Respect\Data\Styles\CakePHP;

class PostCategory
{
    public int $id;

    public Post $post;

    public Category $category;
}
