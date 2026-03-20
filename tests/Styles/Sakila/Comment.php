<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Sakila;

class Comment
{
    public mixed $comment_id = null;

    public mixed $post_id = null;

    public mixed $post = null;

    public string|null $text = null;
}
