<?php

namespace Respect\Data\Styles;

interface Stylable
{
    public function styledName($entityName);

    public function realName($styledName);

    public function styledProperty($name);

    public function realProperty($name);

    public function identifier($name);

    public function remoteIdentifier($name);

    public function remoteFromIdentifier($name);

    public function isRemoteIdentifier($name);

    public function composed($left, $right);
}
