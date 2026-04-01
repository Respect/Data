<?php

declare(strict_types=1);

namespace Respect\Data\Stubs\Immutable;

final readonly class Author
{
    public function __construct(
        public int $id,
        public string $name,
        public string|null $bio = null,
    ) {
    }
}
