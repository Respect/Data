<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Sakila;

class Post
{
    public mixed $post_id = null;

    public string|null $title = null;

    public string|null $text = null;

    public mixed $author_id = null;

    public mixed $author = null;
}
