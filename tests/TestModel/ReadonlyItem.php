<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;

/**
 * Test fixture for FIELDS_READONLY testing.
 */
class ReadonlyItem extends Item
{
    public const ID_FIELD = 'userId';

    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
    ];

    public const FIELDS_READONLY = [
        'createdAt' => Field\Datetime::class,
        'updatedAt' => Field\Datetime::class,
    ];
}
