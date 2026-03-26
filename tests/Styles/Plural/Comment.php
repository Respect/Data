<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Plural;

class Comment
{
    public int $id;

    public Post $post;

    public string|null $text = null;
}
