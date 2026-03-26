<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Sakila;

class Post
{
    public int $postId;

    public string|null $title = null;

    public string|null $text = null;

    public Author $author;
}
