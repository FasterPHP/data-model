<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use PHPUnit\Framework\TestCase;
use FasterPhp\DataModel\TestModel\ExternalItem;

/**
 * Tests for FIELDS_EXTERNAL functionality.
 */
class ExternalFieldsTest extends TestCase
{
    /**
     * Test that external fields can be read via getter.
     */
    public function testExternalFieldCanBeRead(): void
    {
        $item = new ExternalItem([
            'id' => 1,
            'name' => 'Alice',
            'departmentId' => 10,
            'departmentName' => 'Engineering',
        ]);

        $this->assertSame('Alice', $item->getName());
        $this->assertSame(10, $item->getDepartmentId());
        $this->assertSame('Engineering', $item->getDepartmentName());
    }

    /**
     * Test that external fields are NOT included in getSqlValues().
     */
    public function testExternalFieldsExcludedFromGetSqlValues(): void
    {
        $item = new ExternalItem([
            'id' => 1,
            'name' => 'Alice',
            'departmentId' => 10,
            'departmentName' => 'Engineering',
        ]);

        $sqlValues = $item->getSqlValues(true);

        // Should include regular fields but NOT external fields
        $this->assertArrayHasKey('id', $sqlValues);
        $this->assertArrayHasKey('name', $sqlValues);
        $this->assertArrayHasKey('departmentId', $sqlValues);
        $this->assertArrayNotHasKey('departmentName', $sqlValues);

        $this->assertSame(1, $sqlValues['id']);
        $this->assertSame('Alice', $sqlValues['name']);
        $this->assertSame(10, $sqlValues['departmentId']);
    }

    /**
     * Test that setting an external field throws an exception.
     */
    public function testSetExternalFieldThrowsException(): void
    {
        $item = new ExternalItem([
            'id' => 1,
            'name' => 'Alice',
            'departmentId' => 10,
            'departmentName' => 'Engineering',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot update value for read-only field 'departmentName'");

        $item->setDepartmentName('Sales');
    }

    /**
     * Test that setting an external field via magic setter throws exception.
     */
    public function testSetExternalFieldViaMagicSetterThrowsException(): void
    {
        $item = new ExternalItem([
            'id' => 1,
            'name' => 'Alice',
            'departmentId' => 10,
            'departmentName' => 'Engineering',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot update value for read-only field 'departmentName'");

        $item->setDepartmentName('Marketing');
    }

    /**
     * Test that external field exception doesn't mark item as dirty.
     */
    public function testExternalFieldExceptionDoesNotMarkItemDirty(): void
    {
        $item = new ExternalItem([
            'id' => 1,
            'name' => 'Alice',
            'departmentId' => 10,
            'departmentName' => 'Engineering',
        ]);

        $this->assertFalse($item->isDirty());

        try {
            $item->setDepartmentName('Sales');
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
        $item = new ExternalItem([
            'id' => 1,
            'name' => 'Alice',
            'departmentId' => 10,
            'departmentName' => 'Engineering',
        ]);

        $this->assertFalse($item->isDirty());

        // Setting regular fields should work
        $item->setName('Bob');
        $item->setDepartmentId(20);

        $this->assertTrue($item->isDirty());
        $this->assertSame('Bob', $item->getName());
        $this->assertSame(20, $item->getDepartmentId());

        // External field should still be accessible
        $this->assertSame('Engineering', $item->getDepartmentName());
    }

    /**
     * Test that external fields are excluded from getChangedSqlValues().
     */
    public function testExternalFieldsExcludedFromGetChangedSqlValues(): void
    {
        $item = new ExternalItem([
            'id' => 1,
            'name' => 'Alice',
            'departmentId' => 10,
            'departmentName' => 'Engineering',
        ]);

        // Change a regular field
        $item->setName('Bob');

        $changedValues = $item->getChangedSqlValues();

        // Should only include changed regular fields, not external fields
        $this->assertArrayHasKey('name', $changedValues);
        $this->assertArrayNotHasKey('departmentName', $changedValues);
    }
}
