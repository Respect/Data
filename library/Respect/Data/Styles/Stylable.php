<?php

namespace Respect\Data\Styles;

interface Stylable
{

    function styledName($entityName);

    function realName($styledName);

    function styledProperty($name);

    function realProperty($name);

    function identifier($name);

    function remoteIdentifier($name);

    function remoteFromIdentifier($name);
    
    function isRemoteIdentifier($name);

    function composed($left, $right);

}

