<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use Laminas\Validator;
use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Validation\LaminasValidatorTrait;
use FasterPhp\DataModel\Validation\ValidatableTrait;

/**
 * Test fixture for skipIfEmpty validator edge case.
 */
class SkipIfEmptyItem extends Item
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

        if (empty($this->getField('name')->getValue())) {
            return $chain;
        }

        $this->attachValidator(
            $chain,
            new Validator\StringLength(['min' => 3, 'max' => 60]),
        );

        return $chain;
    }
}
