<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Plural;

class Post
{
    public int $id;

    public string|null $title = null;

    public string|null $text = null;

    public Author $author;
}
