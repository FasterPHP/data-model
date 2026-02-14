<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Validation\LaminasValidatorTrait;
use FasterPhp\DataModel\Validation\ValidatableTrait;

/**
 * Test fixture: uses traits but defines no validate*() methods.
 */
class NoValidatorsItem extends Item
{
    use ValidatableTrait;
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
