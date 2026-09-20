<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Item;

/**
 * Test fixture Item for a repository whose SELECT is expressed as a join.
 */
class JoinedItem extends Item
{
    public const ID_FIELD = 'moduleAttemptId';

    public const FIELDS = [
        'moduleId' => Field\Integer::class,
        'courseAttemptId' => Field\Integer::class,
        'currentTime' => Field\Integer::class,
        'markedByUserId' => Field\Integer::class,
    ];

    public const FIELDS_EXTERNAL = [
        'percentWatched' => Field\Integer::class,
        'moduleName' => Field\Varchar::class,
        'markedByUserName' => Field\Varchar::class,
    ];
}
