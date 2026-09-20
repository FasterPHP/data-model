<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Item;

/**
 * Test fixture Item for a joined repository that also injects filters.
 */
class ParticipantItem extends Item
{
    public const ID_FIELD = 'userId';

    public const FIELDS = [
        'role' => Field\Varchar::class,
        'status' => Field\Varchar::class,
    ];

    public const FIELDS_EXTERNAL = [
        'courseStatus' => Field\Varchar::class,
    ];
}
