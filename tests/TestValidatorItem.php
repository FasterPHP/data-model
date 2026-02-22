<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\Validation\LaminasValidatorTrait;
use FasterPhp\DataModel\Validation\ValidatableTrait;
use Laminas\Validator;

/**
 * Test item using default Laminas validators.
 */
class TestValidatorItem extends Item
{
    use ValidatableTrait;
    use LaminasValidatorTrait;

    public const FIELDS = [
        'name' => Field\Varchar::class,
    ];

    public function getName(): ?string
    {
        return $this->getField('name')->getValue();
    }

    public function setName(?string $value): static
    {
        $this->getField('name')->setValue($value);
        return $this;
    }

    protected function validateName(): Validator\ValidatorChain
    {
        $chain = $this->createChain();

        $this->attachValidator(
            $chain,
            new Validator\StringLength(['min' => 3, 'max' => 60]),
        );

        return $chain;
    }
}
