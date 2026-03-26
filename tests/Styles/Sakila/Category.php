<?php

declare(strict_types=1);

namespace Respect\Data\Styles\Sakila;

class Category
{
    public int $categoryId;

    public string|null $name = null;

    public string|null $content = null;

    public string|null $description = null;
}
