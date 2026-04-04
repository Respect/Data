<?php

declare(strict_types=1);

namespace Respect\Data;

use SplObjectStorage;

/** Transforms raw backend data into entity instances mapped to their scopes */
interface Hydrator
{
    public EntityFactory $entityFactory { get; }

    /** Returns just the root entity */
    public function hydrate(
        mixed $raw,
        Scope $scope,
    ): object|false;

    /** @return SplObjectStorage<object, Scope>|false */
    public function hydrateAll(
        mixed $raw,
        Scope $scope,
    ): SplObjectStorage|false;
}
