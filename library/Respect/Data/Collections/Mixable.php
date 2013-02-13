<?php

namespace Respect\Data\Collections;

interface Mixable
{
    public function getMixins(Collection $collection);
    public function mixable(Collection $collection);
}
