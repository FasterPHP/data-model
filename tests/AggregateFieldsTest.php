<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use PHPUnit\Framework\TestCase;
use FasterPhp\DataModel\TestModel\AggregateItem;

/**
 * Tests for FIELDS_AGGREGATE functionality.
 */
class AggregateFieldsTest extends TestCase
{
    /**
     * Test that aggregate fields can be read via getter.
     */
    public function testAggregateFieldCanBeRead(): void
    {
        $item = new AggregateItem(isTemp: false, data: [
            'id' => 1,
            'userId' => 100,
            'status' => 'completed',
            'totalAmount' => 15000,
            'orderCount' => 5,
        ]);

        $this->assertSame('completed', $item->getStatus());
        $this->assertSame(100, $item->getUserId());
        $this->assertSame(15000, $item->getTotalAmount());
        $this->assertSame(5, $item->getOrderCount());
    }

    /**
     * Test that aggregate fields are NOT included in getSqlValues().
     */
    public function testAggregateFieldsExcludedFromGetSqlValues(): void
    {
        $item = new AggregateItem(isTemp: false, data: [
            'id' => 1,
            'userId' => 100,
            'status' => 'completed',
            'totalAmount' => 15000,
            'orderCount' => 5,
        ]);

        $sqlValues = $item->getSqlValues(true);

        // Should include regular fields but NOT aggregate fields or id (implicit)
        $this->assertArrayNotHasKey('id', $sqlValues);
        $this->assertArrayHasKey('userId', $sqlValues);
        $this->assertArrayHasKey('status', $sqlValues);
        $this->assertArrayNotHasKey('totalAmount', $sqlValues);
        $this->assertArrayNotHasKey('orderCount', $sqlValues);

        $this->assertSame(100, $sqlValues['userId']);
        $this->assertSame('completed', $sqlValues['status']);
    }

    /**
     * Test that setting an aggregate field throws an exception.
     */
    public function testSetAggregateFieldThrowsException(): void
    {
        $item = new AggregateItem(isTemp: false, data: [
            'id' => 1,
            'userId' => 100,
            'status' => 'completed',
            'totalAmount' => 15000,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot update value for read-only field 'totalAmount'");

        $item->setTotalAmount(20000);
    }

    /**
     * Test that setting an aggregate field via magic setter throws exception.
     */
    public function testSetAggregateFieldViaMagicSetterThrowsException(): void
    {
        $item = new AggregateItem(isTemp: false, data: [
            'id' => 1,
            'userId' => 100,
            'status' => 'completed',
            'orderCount' => 5,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot update value for read-only field 'orderCount'");

        $item->setOrderCount(10);
    }

    /**
     * Test that aggregate field exception doesn't mark item as dirty.
     */
    public function testAggregateFieldExceptionDoesNotMarkItemDirty(): void
    {
        $item = new AggregateItem(isTemp: false, data: [
            'id' => 1,
            'userId' => 100,
            'status' => 'completed',
            'totalAmount' => 15000,
        ]);

        $this->assertFalse($item->isDirty());

        try {
            $item->setTotalAmount(20000);
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
        $item = new AggregateItem(isTemp: false, data: [
            'id' => 1,
            'userId' => 100,
            'status' => 'completed',
            'totalAmount' => 15000,
        ]);

        $this->assertFalse($item->isDirty());

        // Setting regular fields should work
        $item->setStatus('pending');
        $item->setUserId(200);

        $this->assertTrue($item->isDirty());
        $this->assertSame('pending', $item->getStatus());
        $this->assertSame(200, $item->getUserId());

        // Aggregate field should still be accessible
        $this->assertSame(15000, $item->getTotalAmount());
    }

    /**
     * Test that aggregate fields are excluded from getChangedSqlValues().
     */
    public function testAggregateFieldsExcludedFromGetChangedSqlValues(): void
    {
        $item = new AggregateItem(isTemp: false, data: [
            'id' => 1,
            'userId' => 100,
            'status' => 'completed',
            'totalAmount' => 15000,
        ]);

        // Change a regular field
        $item->setStatus('pending');

        $changedValues = $item->getChangedSqlValues();

        // Should only include changed regular fields, not aggregate fields
        $this->assertArrayHasKey('status', $changedValues);
        $this->assertArrayNotHasKey('totalAmount', $changedValues);
    }
}
