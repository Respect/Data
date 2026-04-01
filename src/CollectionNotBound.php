<?php

declare(strict_types=1);

namespace Respect\Data;

use RuntimeException;

final class CollectionNotBound extends RuntimeException
{
    public function __construct(string|null $collectionName)
    {
        parent::__construct(
            'Collection \'' . ($collectionName ?? '(unnamed)')
            . '\' must be attached to a mapper before fetching or persisting',
        );
    }
}
