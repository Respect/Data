<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

interface Filterable
{
    public function getFilters(Collection $collection);
    public function filterable(Collection $collection);
}
