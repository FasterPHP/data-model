<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

/**
 * Test item with custom validator chain implementation.
 */
class CustomValidatorItem extends Item
{
    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
    ];

    public const VALIDATORS = [
        'name' => [
            ['rule' => 'not_test'], // Custom validator config format
        ],
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

    /**
     * Override buildValidatorChain to use custom validation logic.
     * This demonstrates how frameworks can integrate their own validators.
     */
    protected function buildValidatorChain(string $fieldName, array $configs)
    {
        return new CustomValidatorChain($configs);
    }
}
