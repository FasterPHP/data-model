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
     * Test that a dot-qualified sort field is accepted.
     *
     * @return void
     */
    public function testCreateSortItemWithQualifiedField(): void
    {
        $item = new Sort('users.userId');
        $this->assertEquals('users.userId', $item->getSortField());
    }

    /**
     * Test that a sort field that is not a valid identifier is rejected at construction.
     *
     * @return void
     */
    public function testCreateSortItemWithInvalidSortField(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid sort field 'name` DESC, (SELECT 1)'");
        new Sort('name` DESC, (SELECT 1)');
    }

    /**
     * Test that an invalid sort field is rejected by the setter.
     *
     * @return void
     */
    public function testSetInvalidSortField(): void
    {
        $item = new Sort('name');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid sort field 'COUNT(*)'");
        $item->setSortField('COUNT(*)');
    }

    /**
     * Test that an invalid secondary sort field is rejected.
     *
     * @return void
     */
    public function testSecondarySortFieldIsValidated(): void
    {
        $secondary = new Sort('age');

        // Bypass the setter to simulate a chain carrying an unvalidated field.
        $property = (new \ReflectionClass($secondary))->getProperty('sortField');
        $property->setAccessible(true);
        $property->setValue($secondary, 'age; DROP TABLE users');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid sort field 'age; DROP TABLE users'");
        new Sort('name', Sort::ASCENDING, $secondary);
    }

    /**
     * Test that a valid secondary sort chain is accepted.
     *
     * @return void
     */
    public function testValidSecondarySortChainAccepted(): void
    {
        $third = new Sort('height');
        $second = new Sort('age', Sort::DESCENDING, $third);
        $first = new Sort('users.name', Sort::ASCENDING, $second);

        $this->assertSame($second, $first->getSecondarySort());
        $this->assertSame($third, $second->getSecondarySort());
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
