<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

interface Stylable
{
    public function styledName(string $entityName): string;

    public function realName(string $styledName): string;

    public function styledProperty(string $name): string;

    public function realProperty(string $name): string;

    public function identifier(string $name): string;

    public function remoteIdentifier(string $name): string;

    public function remoteFromIdentifier(string $name): ?string;

    public function isRemoteIdentifier(string $name): bool;

    public function composed(string $left, string $right): string;
}
