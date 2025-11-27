<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;

/**
 * Test fixture for FIELDS_AGGREGATE testing.
 */
class AggregateItem extends Item
{
    public const ID_FIELD = 'orderId';

    public const FIELDS = [
        'id' => Field\Integer::class,
        'userId' => Field\Integer::class,
        'status' => Field\Varchar::class,
    ];

    public const FIELDS_AGGREGATE = [
        'totalAmount' => Field\Integer::class,
        'orderCount' => Field\Integer::class,
    ];
}
