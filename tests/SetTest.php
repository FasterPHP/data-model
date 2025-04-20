<?php

/**
 * Tests for Data Model Set class.
 */

namespace FasterPhp\DataModel;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use FasterPhp\DataModel\TestModel;

/**
 * Tests for Data Model Set class.
 */
class SetTest extends TestCase
{
    protected static array $data = [
        ['id' => 1, 'name' => 'Jack'],
        ['id' => 2, 'name' => 'Jill'],
    ];
    protected static array $items;
    public static function setUpBeforeClass(): void
    {
        self::$items = [
            new TestModel\ValidItem(self::$data[0]),
            new TestModel\ValidItem(self::$data[1]),
        ];
    }

    public function testConstructWithData(): void
    {
        $set = new TestModel\ValidSet(self::$data);
        $this->assertInstanceOf(TestModel\ValidSet::class, $set);
    }

    public function testConstructWithItems(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $this->assertInstanceOf(TestModel\ValidSet::class, $set);
    }

    public function testLazyLoadItems(): void
    {
        $set = new TestModel\ValidSet(self::$data);
        $dataProperty = new ReflectionProperty($set, 'data');
        $this->assertSame(self::$data, $dataProperty->getValue($set));
        $item = $set->current();
        $this->assertInstanceOf(Item::class, $item);
        $this->assertEquals(self::$items[0], $item);
        $this->assertEquals(self::$items[0], $dataProperty->getValue($set)[0]);
        $this->assertSame(self::$data[1], $dataProperty->getValue($set)[1]);
    }

    public function testCount(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $this->assertSame(count(self::$items), count($set));
        $this->assertSame(count(self::$items), $set->count());
    }

    public function testOffsetExists(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $this->assertTrue(isset($set[0]));
        $this->assertTrue(isset($set[1]));
        $this->assertFalse(isset($set[2]));
    }

    public function testOffsetGet(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $this->assertSame(self::$items[0], $set->current());
        $set->next();
        $this->assertSame(self::$items[1], $set->current());
    }

    public function testOffsetSetNullPosition(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $newItem = new TestModel\ValidItem(['id' => 3, 'name' => 'Wendy']);
        $set[] = $newItem;
        $this->assertSame($newItem, $set[2]);
    }

    public function testOffsetSetSpecificPosition(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $newItem = new TestModel\ValidItem(['id' => 3, 'name' => 'Wendy']);
        $set[1] = $newItem;
        $this->assertSame($newItem, $set[1]);
    }

    public function testOffsetUnset(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        unset($set[1]);
        $this->assertCount(1, $set);
    }
}
