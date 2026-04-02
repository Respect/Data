<?php

declare(strict_types=1);

namespace Respect\Data\Collections;

class Collection
{
    public private(set) Collection|null $parent = null;

    /** @var list<Collection> */
    public private(set) array $with;

    public bool $hasChildren { get => !empty($this->with); }

    /**
     * @param list<Collection> $with
     * @param array<scalar, mixed>|scalar|null $filter
     */
    public function __construct(
        public readonly string|null $name = null,
        array $with = [],
        public readonly array|int|float|string|bool|null $filter = null,
        public readonly bool $required = false,
    ) {
        $this->with = $this->adoptChildren($with);
    }

    /**
     * @param list<Collection> $with
     * @param array<scalar, mixed>|scalar|null $filter
     */
    public function derive(
        array $with = [],
        array|int|float|string|bool|null $filter = null,
        bool|null $required = null,
    ): static {
        return new static(
            $this->name,
            ...$this->deriveArgs(
                with: $with,
                filter: $filter,
                required: $required,
            ),
        );
    }

    /**
     * @param list<Collection> $with
     * @param array<scalar, mixed>|scalar|null $filter
     *
     * @return array{with: list<Collection>, filter: array|int|float|string|bool|null, required: bool}
     */
    protected function deriveArgs( // @phpstan-ignore missingType.iterableValue
        array $with = [],
        array|int|float|string|bool|null $filter = null,
        bool|null $required = null,
    ): array {
        return [
            'with' => [...$this->with, ...$with],
            'filter' => $filter ?? $this->filter,
            'required' => $required ?? $this->required,
        ];
    }

    /**
     * @param list<Collection> $children
     *
     * @return list<Collection>
     */
    private function adoptChildren(array $children): array
    {
        $adopted = [];
        foreach ($children as $child) {
            $c = clone $child;
            $c->parent = $this;
            $adopted[] = $c;
        }

        return $adopted;
    }

    /** @param array<int, mixed> $arguments */
    public static function __callStatic(string $name, array $arguments): static
    {
        return new static($name, ...$arguments);
    }

    public function __clone(): void
    {
        $this->with = $this->adoptChildren($this->with);
        $this->parent = null;
    }
}
