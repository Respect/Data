<?php

declare(strict_types=1);

namespace Respect\Data;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use Respect\Data\Collections\Collection;

use function array_filter;
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

    /** @return RecursiveIteratorIterator<CollectionIterator>&iterable<string, Collection> */
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
        return $this->current()->hasMore;
    }

    public function getChildren(): RecursiveArrayIterator
    {
        $c = $this->current();
        $pool = $c->hasChildren ? $c->children : [];
        if ($c->connectsTo !== null) {
            $pool[] = $c->connectsTo;
        }

        return new static(
            array_filter($pool, static fn(Collection $c): bool => $c->name !== null),
            $this->namesCounts,
        );
    }
}
