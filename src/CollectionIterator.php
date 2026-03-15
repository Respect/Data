<?php

declare(strict_types=1);

namespace Respect\Data;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

final class CollectionIterator extends RecursiveArrayIterator
{
    /** @var array<string, int> */
    protected array $namesCounts = [];

    public static function recursive(mixed $target): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(new static($target), 1);
    }

    public function __construct(mixed $target = [], array &$namesCounts = [])
    {
        $this->namesCounts = &$namesCounts;
        parent::__construct(is_array($target) ? $target : [$target]);
    }

    public function key(): string|int|null
    {
        $name = $this->current()->getName();

        if (isset($this->namesCounts[$name])) {
            return $name.++$this->namesCounts[$name];
        }

        $this->namesCounts[$name] = 1;

        return $name;
    }

    public function hasChildren(): bool
    {
        return $this->current()->hasMore();
    }

    public function getChildren(): RecursiveArrayIterator
    {
        $c = $this->current();
        $pool = [];

        if ($c->hasChildren()) {
            $pool = $c->getChildren();
        }

        if ($c->hasNext()) {
            $pool[] = $c->getNext();
        }

        return new static($pool, $this->namesCounts);
    }
}
