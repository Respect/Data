<?php

declare(strict_types=1);

namespace Respect\Data\Styles\NorthWind;

class Comments
{
    public int $CommentID;

    public Posts $Post;

    public string|null $Text = null;
}
