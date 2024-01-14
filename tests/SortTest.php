<?php
/**
 * Tests for Sort class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel;

/**
 * Tests for Sort class.
 */
class SortTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * Test creating a sort item.
	 *
	 * @return void
	 */
	public function testCreateSortItem(): void
	{
		$item = new Sort('name');
		$this->assertEquals(Sort::ASCENDING, $item->getSortDirection());
		$this->assertEquals('name', $item->getSortField());
	}

	/**
	 * Test creating a sort item with a specified direction.
	 *
	 * @return void
	 */
	public function testCreateSortItemWithDirection(): void
	{
		$item = new Sort('wibble', Sort::DESCENDING);
		$this->assertEquals(Sort::DESCENDING, $item->getSortDirection());
		$this->assertEquals('wibble', $item->getSortField());
	}

	/**
	 * Test creating a sort item with a specified sort field and invalid direction.
	 *
	 * @return void
	 */
	public function testCreateSortItemWithSortFieldAndInvalidDirection(): void
	{
		$this->expectException(\FasterPhp\DataModel\Exception::class);
		$this->expectExceptionMessage("Invalid sort direction 'blah'");

		$item = new Sort('wibble', 'blah');
	}

	/**
	 * Test set sort field.
	 *
	 * @return void
	 */
	public function testSetSortField(): void
	{
		$item = new Sort('name');
		$this->assertEquals('name', $item->getSortField());
		$item->setSortField('wibble');
		$this->assertEquals('wibble', $item->getSortField());
	}

	/**
	 * Test set sort direction.
	 *
	 * @return void
	 */
	public function testSetSortDirection(): void
	{
		$item = new Sort('name');
		$this->assertEquals(Sort::ASCENDING, $item->getSortDirection());
		$item->setSortDirection(Sort::DESCENDING);
		$this->assertEquals(Sort::DESCENDING, $item->getSortDirection());
	}

	/**
	 * Test set invalid direction.
	 *
	 * @return void
	 */
	public function testSetInvalidSortDirection(): void
	{
		$item = new Sort('name');

		$this->expectException(\FasterPhp\DataModel\Exception::class);
		$this->expectExceptionMessage("Invalid sort direction 'foo'");

		$item->setSortDirection('foo');
	}


	/**
	 * Test that null is returned if no secondary sort is set on a sort item.
	 *
	 * @return void
	 */
	public function testSecondarySortEmptyReturnsNull(): void
	{
		$item = new Sort('name');
		$this->assertNull($item->getSecondarySort());
	}

	/**
	 * Test setting of a secondary sort using the setter.
	 *
	 * @return void
	 */
	public function testSetSecondarySort(): void
	{
		$itemA = new Sort('name');
		$itemB = new Sort('age');
		$itemA->setSecondarySort($itemB);
		$this->assertSame($itemB, $itemA->getSecondarySort());
	}

	/**
	 * Test setting of a secondary sort using the constructor.
	 *
	 * @return void
	 */
	public function testSecondarySortAssignedInConstructor(): void
	{
		$itemB = new Sort('name');
		$itemA = new Sort('age', Sort::ASCENDING, $itemB);
		$this->assertSame($itemB, $itemA->getSecondarySort());
	}
}
