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
	protected static array $_data = [
		['id' => 1, 'name' => 'Jack'],
		['id' => 2, 'name' => 'Jill'],
	];
	protected static array $_items;

	public static function setUpBeforeClass(): void
	{
		self::$_items = [
			new TestModel\ValidItem(self::$_data[0]),
			new TestModel\ValidItem(self::$_data[1]),
		];
	}

	public function testConstructWithData(): void
	{
		$set = new TestModel\ValidSet(self::$_data);

		$this->assertInstanceOf(TestModel\ValidSet::class, $set);
	}

	public function testConstructWithItems(): void
	{
		$set = new TestModel\ValidSet(self::$_items);

		$this->assertInstanceOf(TestModel\ValidSet::class, $set);
	}

	public function testLazyLoadItems(): void
	{
		$set = new TestModel\ValidSet(self::$_data);

		$dataProperty = new ReflectionProperty($set, '_data');

		$this->assertSame(self::$_data, $dataProperty->getValue($set));

		$item = $set->current();
		$this->assertInstanceOf(Item::class, $item);
		$this->assertEquals(self::$_items[0], $item);
		$this->assertEquals(self::$_items[0], $dataProperty->getValue($set)[0]);
		$this->assertSame(self::$_data[1], $dataProperty->getValue($set)[1]);
	}

	public function testCount(): void
	{
		$set = new TestModel\ValidSet(self::$_items);

		$this->assertSame(count(self::$_items), count($set));
		$this->assertSame(count(self::$_items), $set->count());
	}

	public function testOffsetExists(): void
	{
		$set = new TestModel\ValidSet(self::$_items);

		$this->assertTrue(isset($set[0]));
		$this->assertTrue(isset($set[1]));
		$this->assertFalse(isset($set[2]));
	}

	public function testOffsetGet(): void
	{
		$set = new TestModel\ValidSet(self::$_items);

		$this->assertSame(self::$_items[0], $set->current());
		$set->next();
		$this->assertSame(self::$_items[1], $set->current());
	}

	public function testOffsetSetNullPosition(): void
	{
		$set = new TestModel\ValidSet(self::$_items);

		$newItem = new TestModel\ValidItem(['id' => 3, 'name' => 'Wendy']);
		$set[] = $newItem;

		$this->assertSame($newItem, $set[2]);
	}

	public function testOffsetSetSpecificPosition(): void
	{
		$set = new TestModel\ValidSet(self::$_items);

		$newItem = new TestModel\ValidItem(['id' => 3, 'name' => 'Wendy']);
		$set[1] = $newItem;

		$this->assertSame($newItem, $set[1]);
	}

	public function testOffsetUnset(): void
	{
		$set = new TestModel\ValidSet(self::$_items);

		unset($set[1]);

		$this->assertCount(1, $set);
	}
}
