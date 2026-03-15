<?php

declare(strict_types=1);

namespace Respect\Data;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Respect\Data\Collections\Collection;

use function is_array;

/** @extends RecursiveArrayIterator<array-key, Collection> */
final class CollectionIterator extends RecursiveArrayIterator
{
    /** @var array<string, int> */
    protected array $namesCounts = [];

    /** @param array<string, int> $namesCounts */
    public function __construct(mixed $target = [], array &$namesCounts = [])
    {
        $this->namesCounts = &$namesCounts;

        /** @var array<Collection> $items */
        $items = is_array($target) ? $target : [$target];

        parent::__construct($items);
    }

    /** @return RecursiveIteratorIterator<CollectionIterator> */
    public static function recursive(mixed $target): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(new self($target), 1);
    }

    public function key(): string
    {
        $name = $this->current()->getName() ?? '';

        if (isset($this->namesCounts[$name])) {
            return $name . ++$this->namesCounts[$name];
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
