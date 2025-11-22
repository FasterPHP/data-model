<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\Tests;

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;
use PHPUnit\Framework\TestCase;

/**
 * Tests for validator extension hook functionality.
 */
final class ValidationExtensionTest extends TestCase
{
    public function testDefaultValidatorChain(): void
    {
        $item = new TestValidatorItem();
        $item->setName('ab'); // Too short
        
        $this->assertFalse($item->isValid());
        $errors = $item->getValidationErrors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertContains('The input is less than 3 characters long', $errors['name']);
    }

    public function testCustomValidatorChain(): void
    {
        $item = new CustomValidatorItem();
        $item->setName('test');
        
        $this->assertFalse($item->isValid());
        $errors = $item->getValidationErrors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertSame(['Custom validation failed for: test'], $errors['name']);
    }

    public function testCustomValidatorChainSuccess(): void
    {
        $item = new CustomValidatorItem();
        $item->setName('valid');
        
        $this->assertTrue($item->isValid());
        $this->assertSame([], $item->getValidationErrors());
    }
}

/**
 * Test item using default Laminas validators.
 */
class TestValidatorItem extends Item
{
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

/**
 * Custom validator chain that mimics the interface expected by Item::validate().
 */
class CustomValidatorChain
{
    private array $configs;
    private array $messages = [];

    public function __construct(array $configs)
    {
        $this->configs = $configs;
    }

    public function isValid($value): bool
    {
        $this->messages = [];
        
        foreach ($this->configs as $config) {
            if (isset($config['rule']) && $config['rule'] === 'not_test') {
                if ($value === 'test') {
                    $this->messages[] = "Custom validation failed for: {$value}";
                    return false;
                }
            }
        }
        
        return true;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}
