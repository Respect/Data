<?php

declare(strict_types=1);

namespace Respect\Data\Hydrators;

use Respect\Data\Collections\Collection;
use Respect\Data\EntityFactory;
use Respect\Data\Hydrator;
use SplObjectStorage;

/** Base hydrator providing FK-to-entity wiring shared by all strategies */
abstract class Base implements Hydrator
{
    /** @param SplObjectStorage<object, Collection> $entities */
    protected function wireRelationships(SplObjectStorage $entities, EntityFactory $entityFactory): void
    {
        $style = $entityFactory->style;
        $entitiesClone = clone $entities;

        foreach ($entities as $instance) {
            foreach ($entityFactory->extractProperties($instance) as $field => $v) {
                if (!$style->isRemoteIdentifier($field)) {
                    continue;
                }

                foreach ($entitiesClone as $sub) {
                    $tableName = (string) $entities[$sub]->name;
                    $primaryName = $style->identifier($tableName);

                    if (
                        $tableName !== $style->remoteFromIdentifier($field)
                            || $entityFactory->get($sub, $primaryName) != $v
                    ) {
                        continue;
                    }

                    $v = $sub;
                }

                $entityFactory->set($instance, $field, $v);
            }
        }
    }
}
