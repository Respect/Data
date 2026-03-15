<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

interface Mixable
{
    public function getMixins(Collection $collection): mixed;
    public function mixable(Collection $collection): mixed;
}
