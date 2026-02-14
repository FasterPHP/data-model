<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\Validation\ValidatableTrait;

/**
 * Test item with custom validator chain implementation (no LaminasValidatorTrait).
 * Demonstrates that ValidatableTrait works independently with any chain-like object.
 */
class CustomValidatorItem extends Item
{
    use ValidatableTrait;

    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
    ];

    public function getId(): ?int
    {
        return $this->getField('id')->getValue();
    }

    public function setId(?int $value): static
    {
        $this->getField('id')->setValue($value);
        return $this;
    }

    public function getName(): ?string
    {
        return $this->getField('name')->getValue();
    }

    public function setName(?string $value): static
    {
        $this->getField('name')->setValue($value);
        return $this;
    }

    protected function validateName(): CustomValidatorChain
    {
        return new CustomValidatorChain([
            ['rule' => 'not_test'],
        ]);
    }
}
