<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Validation\LaminasValidatorTrait;

/**
 * Test fixture: uses LaminasValidatorTrait but does not define VALIDATORS.
 */
class NoValidatorsItem extends Item
{
    use LaminasValidatorTrait;

    public const ID_FIELD = 'id';

    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
    ];

    public const DEFAULTS = [
        'name' => '',
    ];
}
