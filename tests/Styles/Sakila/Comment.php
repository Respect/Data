<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Sakila;

class Comment
{
    public int $commentId;

    public Post $post;

    public string|null $text = null;
}
