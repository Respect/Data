<?php

declare(strict_types=1);

namespace Respect\Data\Stubs;

class TypedEntity
{
    public string|null $value = null;

    public function __construct()
    {
        $this->value = 'constructed';
    }
}
