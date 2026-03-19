<?php

declare(strict_types=1);

namespace Respect\Data;

use Respect\Data\Collections\Collection;
use SplObjectStorage;

/** Transforms raw backend data into entity instances mapped to their collections */
interface Hydrator
{
    /** @return SplObjectStorage<object, Collection>|false */
    public function hydrate(
        mixed $raw,
        Collection $collection,
        EntityFactory $entityFactory,
    ): SplObjectStorage|false;
}
