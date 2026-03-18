<?php

declare(strict_types=1);

namespace Respect\Data\Stubs;

use Respect\Data\NotPersistable;

class EntityWithExcluded
{
    public string $name = '';

    #[NotPersistable]
    public string $secret = 'hidden';
}
