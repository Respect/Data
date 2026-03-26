<?php

declare(strict_types=1);

namespace Respect\Data\Styles\CakePHP;

class Comment
{
    public int $id;

    public Post $post;

    public string|null $text = null;
}
