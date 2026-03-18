<?php

declare(strict_types=1);

namespace Respect\Data;

use Attribute;

/** Marks a property as excluded from persistence operations */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class NotPersistable
{
}
