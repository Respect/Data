<?php

declare(strict_types=1);

namespace Respect\Data\Stubs\Immutable;

final readonly class Comment
{
    public function __construct(
        public int $id,
        public string|null $text = null,
        public Post|null $post = null,
    ) {
    }
}
