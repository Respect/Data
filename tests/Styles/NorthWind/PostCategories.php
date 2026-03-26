<?php

declare(strict_types=1);

namespace Respect\Data\Styles\NorthWind;

class PostCategories
{
    public int $PostCategoryID;

    public Posts $Post;

    public Categories $Category;
}
