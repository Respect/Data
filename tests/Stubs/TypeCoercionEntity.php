<?php

declare(strict_types=1);

namespace Respect\Data\Stubs;

class TypeCoercionEntity
{
    public int $id;

    /** Union type with scalar and null */
    public int|string|null $flexible = null;

    /** Non-nullable int — coercion rejection target */
    public int $strict;

    /** Union without string — forces lossy coercion for numeric strings */
    public int|float $narrowUnion;
}
