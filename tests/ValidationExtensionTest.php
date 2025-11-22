<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\Tests;

require_once __DIR__ . '/TestValidatorItem.php';
require_once __DIR__ . '/CustomValidatorItem.php';
require_once __DIR__ . '/CustomValidatorChain.php';

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
