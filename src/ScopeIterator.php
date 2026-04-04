<?php

declare(strict_types=1);

namespace Respect\Data;

use RecursiveArrayIterator;
use RecursiveIteratorIterator;

use function is_array;

/** @extends RecursiveArrayIterator<array-key, Scope> */
final class ScopeIterator extends RecursiveArrayIterator
{
    /** @var array<string, int> */
    protected array $namesCounts = [];

    /**
     * @param Scope|array<Scope> $target
     * @param array<string, int> $namesCounts
     */
    public function __construct(Scope|array $target = [], array &$namesCounts = [])
    {
        $this->namesCounts = &$namesCounts;

        $items = is_array($target) ? $target : [$target];

        parent::__construct($items);
    }

    /** @return RecursiveIteratorIterator<ScopeIterator>&iterable<string, Scope> */
    public static function recursive(Scope $target): RecursiveIteratorIterator
    {
        return new RecursiveIteratorIterator(new self($target), 1);
    }

    public function key(): string
    {
        $name = $this->current()->name;

        if (isset($this->namesCounts[$name])) {
            return $name . ++$this->namesCounts[$name];
        }

        $this->namesCounts[$name] = 1;

        return $name;
    }

    public function hasChildren(): bool
    {
        return $this->current()->hasChildren;
    }

    public function getChildren(): RecursiveArrayIterator
    {
        return new static(
            $this->current()->with,
            $this->namesCounts,
        );
    }
}
