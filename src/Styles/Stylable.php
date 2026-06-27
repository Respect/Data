<?php

declare(strict_types=1);

namespace Respect\Data\Styles;

/**
 * Maps between PHP-side identifiers (scope names, properties) and DB-side
 * identifiers (tables, columns). The naming follows a quadrant: `styled*`
 * produces a PHP identifier, `real*` produces a DB identifier; `*Name` works at
 * the entity level (class/table), `*Property` at the field level
 * (property/column).
 */
interface Stylable
{
    /** Scope name → entity class short name (`postTag` → `PostTag`). */
    public function styledName(string $name): string;

    /** Scope name → DB table name (`postTag` → `post_tag`, or `posts` under Plural). */
    public function realName(string $name): string;

    /** DB column → PHP property (`post_id` → `postId`). */
    public function styledProperty(string $name): string;

    /** PHP property → DB column (`postId` → `post_id`). */
    public function realProperty(string $name): string;

    /** Scope name → primary key column. */
    public function identifier(string $name): string;

    /** Scope name → foreign key column (`post` → `post_id`). */
    public function remoteIdentifier(string $name): string;

    /** Is this column a foreign key? */
    public function isRemoteIdentifier(string $name): bool;

    /** Foreign key column → relation scope name (`post_id` → `post`), or null. */
    public function relationProperty(string $remoteIdentifierField): string|null;

    /** Two scope names → junction table name (`post`, `tag` → `post_tag`). */
    public function composed(string $left, string $right): string;
}
