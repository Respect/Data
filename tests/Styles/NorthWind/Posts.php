<?php

declare(strict_types=1);

namespace Respect\Data\Styles\NorthWind;

class Posts
{
    public int $PostID;

    public string|null $Title = null;

    public string|null $Text = null;

    public Authors $Author;
}
