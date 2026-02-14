<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Validation\LaminasValidatorTrait;

/**
 * Test fixture for missing validator class edge case.
 */
class MissingClassItem extends Item
{
    use LaminasValidatorTrait;

    public const ID_FIELD = 'id';

    public const FIELDS = [
        'id' => Field\Integer::class,
        'noclass' => Field\Varchar::class,
    ];

    public const DEFAULTS = [
        'noclass' => 'some value',
    ];

    public const VALIDATORS = [
        'noclass' => [
            [
                'options' => ['min' => 1],
            ],
        ],
    ];
}
