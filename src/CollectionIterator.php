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

    /**
     * @param Collection|array<Collection> $target
     * @param array<string, int> $namesCounts
     */
    public function __construct(Collection|array $target = [], array &$namesCounts = [])
    {
        $this->namesCounts = &$namesCounts;

        $items = is_array($target) ? $target : [$target];

        parent::__construct($items);
    }

    /** @return RecursiveIteratorIterator<CollectionIterator> */
    public static function recursive(Collection $target): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(new self($target), 1);
    }

    public function key(): string
    {
        $name = $this->current()->name ?? '';

        if (isset($this->namesCounts[$name])) {
            return $name . ++$this->namesCounts[$name];
        }

        $this->namesCounts[$name] = 1;

        return $name;
    }

    public function hasChildren(): bool
    {
        return $this->current()->more;
    }

    public function getChildren(): RecursiveArrayIterator
    {
        $c = $this->current();
        $pool = $c->hasChildren ? $c->children : [];
        if ($c->next !== null) {
            $pool[] = $c->next;
        }

        return new static($pool, $this->namesCounts);
    }
}
