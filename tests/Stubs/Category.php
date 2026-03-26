<?php

declare(strict_types=1);

namespace Respect\Data\Stubs;

class Category
{
    public int $id;

    public string|null $name = null;

    public string|null $label = null;

    public Category $category;
}
