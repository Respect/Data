<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

interface Typable
{
    public function getType(Collection $collection);
    public function typable(Collection $collection);
}
