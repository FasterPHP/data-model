<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Item;

/**
 * Test fixture Item for a repository returning a hand-written query.
 */
class HandWrittenItem extends Item
{
    public const ID_FIELD = 'userId';

    public const FIELDS = [
        'name' => Field\Varchar::class,
    ];

    public const FIELDS_EXTERNAL = [
        'accountName' => Field\Varchar::class,
    ];
}
