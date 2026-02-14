<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use Laminas\Validator;
use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Validation\LaminasValidatorTrait;
use FasterPhp\DataModel\Validation\ValidatableTrait;

/**
 * Test fixture for Callback validator and message override paths.
 */
class CallbackValidatorItem extends Item
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

    protected function validateName(): Validator\ValidatorChain
    {
        $chain = $this->createChain();

        $this->attachValidator(
            $chain,
            new Validator\Callback([
                'callback' => static fn($value) => strlen($value) >= 2,
            ]),
            message: 'Name is invalid',
        );

        return $chain;
    }
}
