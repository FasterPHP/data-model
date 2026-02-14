<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use Laminas\Validator;
use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Validation\LaminasValidatorTrait;

/**
 * Test fixture for Callback validator and message override paths.
 */
class CallbackValidatorItem extends Item
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
                'class' => Validator\Callback::class,
                'options' => [
                    'callback' => [self::class, 'validateName'],
                ],
                'message' => 'Name is invalid',
            ],
        ],
    ];

    public static function validateName($value, $context = null): bool
    {
        return strlen($value) >= 2;
    }
}
