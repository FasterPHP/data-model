<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Item;

/**
 * Test fixture Item for a repository overriding only the from clause hook.
 */
class FromOverrideItem extends Item
{
    public const ID_FIELD = 'userId';

    public const FIELDS = [
        'name' => Field\Varchar::class,
    ];
}
