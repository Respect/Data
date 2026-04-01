<?php

declare(strict_types=1);

namespace Respect\Data\Stubs\Immutable;

final readonly class Post
{
    public function __construct(
        public int $id,
        public string $title,
        public string|null $text = null,
        public Author|null $author = null,
    ) {
    }
}
