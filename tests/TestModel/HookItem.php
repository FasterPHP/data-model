<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Item;

/**
 * Test fixture Item for the repository query hook.
 */
class HookItem extends Item
{
    public const ID_FIELD = 'userId';

    public const FIELDS = [
        'name' => Field\Varchar::class,
        'age' => Field\Integer::class,
    ];
}
