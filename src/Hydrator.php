<?php

declare(strict_types=1);

namespace Respect\Data;

use Respect\Data\Collections\Collection;
use SplObjectStorage;

/** Transforms raw backend data into entity instances mapped to their collections */
interface Hydrator
{
    public EntityFactory $entityFactory { get; }

    /** Returns just the root entity */
    public function hydrate(
        mixed $raw,
        Collection $collection,
    ): object|false;

    /** @return SplObjectStorage<object, Collection>|false */
    public function hydrateAll(
        mixed $raw,
        Collection $collection,
    ): SplObjectStorage|false;
}
