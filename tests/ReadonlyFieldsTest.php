<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use PHPUnit\Framework\TestCase;
use FasterPhp\DataModel\TestModel\ReadonlyItem;

/**
 * Tests for FIELDS_READONLY functionality.
 */
class ReadonlyFieldsTest extends TestCase
{
    /**
     * Test that readonly fields can be read via getter.
     */
    public function testReadonlyFieldCanBeRead(): void
    {
        $item = new ReadonlyItem([
            'id' => 1,
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'createdAt' => '2025-01-01 12:00:00',
            'updatedAt' => '2025-01-02 14:30:00',
        ]);

        $this->assertSame('Alice', $item->getName());
        $this->assertSame('alice@example.com', $item->getEmail());

        // Datetime fields return DateTime objects
        $this->assertInstanceOf(\DateTime::class, $item->getCreatedAt());
        $this->assertSame('2025-01-01 12:00:00', $item->getCreatedAt()->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(\DateTime::class, $item->getUpdatedAt());
        $this->assertSame('2025-01-02 14:30:00', $item->getUpdatedAt()->format('Y-m-d H:i:s'));
    }

    /**
     * Test that readonly fields are included in getSqlValues().
     */
    public function testReadonlyFieldsIncludedInGetSqlValues(): void
    {
        $item = new ReadonlyItem([
            'id' => 1,
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'createdAt' => '2025-01-01 12:00:00',
            'updatedAt' => '2025-01-02 14:30:00',
        ]);

        $sqlValues = $item->getSqlValues(true);

        // Should include both regular fields and readonly fields
        $this->assertArrayHasKey('id', $sqlValues);
        $this->assertArrayHasKey('name', $sqlValues);
        $this->assertArrayHasKey('email', $sqlValues);
        $this->assertArrayHasKey('createdAt', $sqlValues);
        $this->assertArrayHasKey('updatedAt', $sqlValues);

        $this->assertSame(1, $sqlValues['id']);
        $this->assertSame('Alice', $sqlValues['name']);
        $this->assertSame('alice@example.com', $sqlValues['email']);
        $this->assertSame('2025-01-01 12:00:00', $sqlValues['createdAt']);
        $this->assertSame('2025-01-02 14:30:00', $sqlValues['updatedAt']);
    }

    /**
     * Test that setting a readonly field throws an exception.
     */
    public function testSetReadonlyFieldThrowsException(): void
    {
        $item = new ReadonlyItem([
            'id' => 1,
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'createdAt' => '2025-01-01 12:00:00',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot update value for read-only field 'createdAt'");

        $item->setCreatedAt('2025-01-02 12:00:00');
    }

    /**
     * Test that setting a readonly field via magic setter throws exception.
     */
    public function testSetReadonlyFieldViaMagicSetterThrowsException(): void
    {
        $item = new ReadonlyItem([
            'id' => 1,
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'updatedAt' => '2025-01-01 12:00:00',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot update value for read-only field 'updatedAt'");

        $item->setUpdatedAt('2025-01-02 12:00:00');
    }

    /**
     * Test that readonly field exception doesn't mark item as dirty.
     */
    public function testReadonlyFieldExceptionDoesNotMarkItemDirty(): void
    {
        $item = new ReadonlyItem([
            'id' => 1,
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'createdAt' => '2025-01-01 12:00:00',
        ]);

        $this->assertFalse($item->isDirty());

        try {
            $item->setCreatedAt('2025-01-02 12:00:00');
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            // Exception was thrown as expected
        }

        // Item should still not be dirty after exception
        $this->assertFalse($item->isDirty());
        $this->assertEmpty($item->getChangedSqlValues());
    }

    /**
     * Test that regular fields can still be set normally.
     */
    public function testRegularFieldsCanBeSetNormally(): void
    {
        $item = new ReadonlyItem([
            'id' => 1,
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'createdAt' => '2025-01-01 12:00:00',
        ]);

        $this->assertFalse($item->isDirty());

        // Setting regular fields should work
        $item->setName('Bob');
        $item->setEmail('bob@example.com');

        $this->assertTrue($item->isDirty());
        $this->assertSame('Bob', $item->getName());
        $this->assertSame('bob@example.com', $item->getEmail());

        // Readonly field should still be accessible (as DateTime object)
        $this->assertInstanceOf(\DateTime::class, $item->getCreatedAt());
        $this->assertSame('2025-01-01 12:00:00', $item->getCreatedAt()->format('Y-m-d H:i:s'));
    }
}
