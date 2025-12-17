<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\Validation\LaminasValidatorTrait;

/**
 * Test item using default Laminas validators.
 */
class TestValidatorItem extends Item
{
    use LaminasValidatorTrait;

    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
    ];

    public const VALIDATORS = [
        'name' => [
            ['class' => \Laminas\Validator\StringLength::class, 'options' => ['min' => 3, 'max' => 60]],
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
}
