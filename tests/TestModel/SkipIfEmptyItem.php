<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use Laminas\Validator;
use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Validation\LaminasValidatorTrait;

/**
 * Test fixture for skipIfEmpty validator edge case.
 */
class SkipIfEmptyItem extends Item
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

    public const VALIDATORS = [
        'name' => [
            [
                'class' => Validator\StringLength::class,
                'options' => ['min' => 3, 'max' => 60],
                'skipIfEmpty' => true,
            ],
        ],
    ];
}
