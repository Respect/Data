<?php

namespace Respect\Data\Collections;

interface Typable
{
    public function getType(Collection $collection);
    public function typable(Collection $collection);
}
