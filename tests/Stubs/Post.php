<?php

declare(strict_types=1);

namespace Respect\Data\Stubs;

class Post
{
    public mixed $id = null;

    public string|null $title = null;

    public string|null $text = null;

    public mixed $author;
}
