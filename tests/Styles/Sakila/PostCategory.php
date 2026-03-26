<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Sakila;

class PostCategory
{
    public int $postCategoryId;

    public Post $post;

    public Category $category;
}
