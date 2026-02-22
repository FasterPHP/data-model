<?php

/**
 * Tests for Data Model Set class.
 */

namespace FasterPhp\DataModel;

use InvalidArgumentException;
use OutOfBoundsException;
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

    public function testIsEmptyTrue(): void
    {
        $set = new TestModel\ValidSet([]);
        $this->assertTrue($set->isEmpty());
    }

    public function testIsEmptyFalse(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $this->assertFalse($set->isEmpty());
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

    public function testOffsetGetNull(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $this->assertNull($set->offsetGet(null));
    }

    public function testOffsetGetOutOfBounds(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $this->expectException(OutOfBoundsException::class);
        $set->offsetGet(99);
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

    public function testJsonSerialize(): void
    {
        $set = new TestModel\ValidSet(self::$data);
        $this->assertSame($set->getValues(), $set->jsonSerialize());
    }

    public function testToString(): void
    {
        $set = new TestModel\ValidSet(self::$data);
        $this->assertSame(json_encode($set->getValues()), (string) $set);
    }

    public function testGetValues(): void
    {
        $set = new TestModel\ValidSet(self::$data);
        $values = $set->getValues();
        $this->assertCount(2, $values);
        // id is no longer in getValues() — it is implicit and auto-managed
        $this->assertArrayNotHasKey('id', $values[0]);
        $this->assertSame('Jack', $values[0]['name']);
        $this->assertArrayNotHasKey('id', $values[1]);
        $this->assertSame('Jill', $values[1]['name']);
    }

    public function testSeek(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $set->seek(1);
        $this->assertSame(self::$items[1], $set->current());
    }

    public function testSeekOutOfBounds(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $this->expectException(OutOfBoundsException::class);
        $set->seek(99);
    }

    public function testCurrentReturnsFalse(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $set->next();
        $set->next();
        $this->assertFalse($set->current());
    }

    public function testArrayMapKeyAndValue(): void
    {
        $set = new TestModel\ValidSet(self::$data);
        $result = $set->arrayMap('id', 'name');
        $this->assertSame([1 => 'Jack', 2 => 'Jill'], $result);
    }

    public function testArrayMapValueOnly(): void
    {
        $set = new TestModel\ValidSet(self::$data);
        $result = $set->arrayMap(null, 'name');
        $this->assertSame(['Jack', 'Jill'], $result);
    }

    public function testArrayMapKeyOnly(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $result = $set->arrayMap('id');
        $this->assertCount(2, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertInstanceOf(TestModel\ValidItem::class, $result[1]);
        $this->assertInstanceOf(TestModel\ValidItem::class, $result[2]);
    }

    public function testArrayMapNoArgs(): void
    {
        $set = new TestModel\ValidSet(self::$items);
        $result = $set->arrayMap();
        $this->assertCount(2, $result);
        $this->assertInstanceOf(TestModel\ValidItem::class, $result[0]);
        $this->assertInstanceOf(TestModel\ValidItem::class, $result[1]);
    }

    public function testAddItemWrongType(): void
    {
        $set = new TestModel\ValidSet();
        $wrongItem = new TestModel\ExternalItem(['id' => 1, 'name' => 'Wrong']);
        $this->expectException(InvalidArgumentException::class);
        $set->addItem($wrongItem);
    }

    public function testGetItemInvalidData(): void
    {
        $set = new TestModel\ValidSet(['not-an-array-or-item']);
        $this->expectException(Exception::class);
        $set->current();
    }
}
