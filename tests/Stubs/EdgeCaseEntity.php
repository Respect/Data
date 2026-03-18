<?php

declare(strict_types=1);

namespace Respect\Data\Stubs;

class EdgeCaseEntity
{
    public string $initialized = 'hello';

    public string $uninitialized;

    protected string $protected = 'prot_val';

    private string $private = 'priv_val';

    public static string $static = 'should_not_appear';
}
