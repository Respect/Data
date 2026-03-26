<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Plural;

class Category
{
    public int $id;

    public string|null $name = null;

    public Category $category;
}
