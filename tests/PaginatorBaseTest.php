<?php

/**
 * Tests for Paginator\Base class.
 */

namespace FasterPhp\DataModel\Paginator;

use FasterPhp\DataModel\Exception;
use FasterPhp\DataModel\Sort;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Minimal concrete subclass of Base for testing.
 */
class TestPaginator extends Base
{
    public function getItems(): array
    {
        return $this->items ?? [];
    }
}

/**
 * Tests for Paginator\Base class.
 */
class PaginatorBaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Base::setDefaultMaxItemsPerPage(null);
        Base::setDefaultMaxPageLinks(null);
    }

    private function createPaginator(?Sort $sort = null): TestPaginator
    {
        return new TestPaginator($sort);
    }

    public function testDefaultMaxItemsPerPage(): void
    {
        Base::setDefaultMaxItemsPerPage(25);
        $paginator = $this->createPaginator();
        $this->assertSame(25, $paginator->getMaxItemsPerPage());
    }

    public function testDefaultMaxItemsPerPageNull(): void
    {
        $paginator = $this->createPaginator();
        $this->assertNull($paginator->getMaxItemsPerPage());
    }

    public function testInstanceMaxItemsPerPageOverridesDefault(): void
    {
        Base::setDefaultMaxItemsPerPage(25);
        $paginator = $this->createPaginator();
        $paginator->setMaxItemsPerPage(10);
        $this->assertSame(10, $paginator->getMaxItemsPerPage());
    }

    public function testDefaultMaxPageLinks(): void
    {
        Base::setDefaultMaxPageLinks(5);
        $paginator = $this->createPaginator();
        $this->assertSame(5, $paginator->getMaxPageLinks());
    }

    public function testDefaultMaxPageLinksNull(): void
    {
        $paginator = $this->createPaginator();
        $this->assertNull($paginator->getMaxPageLinks());
    }

    public function testMaxPageLinks(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setMaxPageLink(7);
        $this->assertSame(7, $paginator->getMaxPageLinks());
    }

    public function testMaxPageLinksOverridesDefault(): void
    {
        Base::setDefaultMaxPageLinks(5);
        $paginator = $this->createPaginator();
        $paginator->setMaxPageLink(7);
        $this->assertSame(7, $paginator->getMaxPageLinks());
    }

    public function testSetPageNumClampsToOne(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setPageNum(0);
        $this->assertSame(1, $paginator->getPageNum());

        $paginator->setPageNum(-5);
        $this->assertSame(1, $paginator->getPageNum());
    }

    public function testSetPageNumValid(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setPageNum(3);
        $this->assertSame(3, $paginator->getPageNum());
    }

    public function testGetNumPagesNoLimit(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsTotal(100);
        $this->assertSame(1, $paginator->getNumPages());
    }

    public function testGetNumPagesWithLimit(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsTotal(25);
        $paginator->setMaxItemsPerPage(10);
        $this->assertSame(3, $paginator->getNumPages());
    }

    public function testGetNumPagesZeroItems(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsTotal(0);
        $paginator->setMaxItemsPerPage(10);
        $this->assertSame(1, $paginator->getNumPages());
    }

    public function testNumItemsOnPage(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsOnPage(5);
        $this->assertSame(5, $paginator->getNumItemsOnPage());
    }

    public function testGetNumItemsTotalThrows(): void
    {
        $paginator = $this->createPaginator();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Num items total not set');
        $paginator->getNumItemsTotal();
    }

    public function testNumItemsTotal(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsTotal(42);
        $this->assertSame(42, $paginator->getNumItemsTotal());
    }

    public function testGetFirstItemNum(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsTotal(30);
        $paginator->setMaxItemsPerPage(10);

        $paginator->setPageNum(1);
        $this->assertSame(1, $paginator->getFirstItemNum());

        $paginator->setPageNum(2);
        $paginator->setNumItemsTotal(30); // reset cached numPages
        $this->assertSame(11, $paginator->getFirstItemNum());
    }

    public function testGetFirstItemNumEmpty(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsTotal(0);
        $paginator->setMaxItemsPerPage(10);
        $this->assertSame(0, $paginator->getFirstItemNum());
    }

    public function testGetLastItemNum(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsTotal(30);
        $paginator->setMaxItemsPerPage(10);
        $paginator->setPageNum(1);
        $paginator->setNumItemsOnPage(10);
        $this->assertSame(10, $paginator->getLastItemNum());
    }

    public function testGetLastItemNumEmpty(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsTotal(0);
        $paginator->setMaxItemsPerPage(10);
        $paginator->setNumItemsOnPage(0);
        $this->assertSame(0, $paginator->getLastItemNum());
    }

    public function testGetFirstPageLinkNumNoMaxPageLinks(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsTotal(100);
        $paginator->setMaxItemsPerPage(10);
        $paginator->setPageNum(5);
        $this->assertSame(1, $paginator->getFirstPageLinkNum());
    }

    public function testGetFirstPageLinkNumWithMaxPageLinks(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsTotal(100);
        $paginator->setMaxItemsPerPage(10);
        $paginator->setMaxPageLink(5);

        $paginator->setPageNum(1);
        $paginator->setNumItemsTotal(100);
        $this->assertSame(1, $paginator->getFirstPageLinkNum());

        $paginator->setPageNum(5);
        $paginator->setNumItemsTotal(100);
        $this->assertSame(1, $paginator->getFirstPageLinkNum());

        $paginator->setPageNum(6);
        $paginator->setNumItemsTotal(100);
        $this->assertSame(6, $paginator->getFirstPageLinkNum());
    }

    public function testGetLastPageLinkNumNoMaxPageLinks(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsTotal(50);
        $paginator->setMaxItemsPerPage(10);
        $paginator->setPageNum(1);
        $this->assertSame(5, $paginator->getLastPageLinkNum());
    }

    public function testGetLastPageLinkNumWithMaxPageLinks(): void
    {
        $paginator = $this->createPaginator();
        $paginator->setNumItemsTotal(100);
        $paginator->setMaxItemsPerPage(10);
        $paginator->setMaxPageLink(5);

        $paginator->setPageNum(1);
        $paginator->setNumItemsTotal(100);
        $this->assertSame(5, $paginator->getLastPageLinkNum());

        $paginator->setPageNum(6);
        $paginator->setNumItemsTotal(100);
        $this->assertSame(10, $paginator->getLastPageLinkNum());
    }

    public function testGetSort(): void
    {
        $sort = new Sort('name', Sort::ASCENDING);
        $paginator = $this->createPaginator($sort);
        $this->assertSame($sort, $paginator->getSort());
    }

    public function testGetSortNull(): void
    {
        $paginator = $this->createPaginator();
        $this->assertNull($paginator->getSort());
    }

    /*
     * SqlPaginator-specific tests
     */
    private function createMockPdo(): PDO
    {
        return $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock();
    }

    public function testSqlPaginatorSetPdo(): void
    {
        $pdo1 = $this->createMockPdo();
        $pdo2 = $this->createMockPdo();

        $paginator = new SqlPaginator($pdo1);
        $result = $paginator->setPdo($pdo2);
        $this->assertSame($paginator, $result);
    }

    public function testSqlPaginatorSetSql(): void
    {
        $pdo = $this->createMockPdo();
        $paginator = new SqlPaginator($pdo);
        $result = $paginator->setSql('SELECT 1');
        $this->assertSame($paginator, $result);

        // Setting the same SQL should not clear results
        $paginator->setSql('SELECT 1');
    }

    public function testSqlPaginatorSetParams(): void
    {
        $pdo = $this->createMockPdo();
        $paginator = new SqlPaginator($pdo);
        $result = $paginator->setParams([':id' => 1]);
        $this->assertSame($paginator, $result);
    }

    public function testSqlPaginatorGetSqlThrows(): void
    {
        $pdo = $this->createMockPdo();
        $paginator = new SqlPaginator($pdo);
        $paginator->setParams([]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('SQL not set');
        $paginator->getItems();
    }

    public function testSqlPaginatorGetParamsThrows(): void
    {
        $pdo = $this->createMockPdo();
        $stmt = $this->getMockBuilder(\PDOStatement::class)
            ->disableOriginalConstructor()
            ->getMock();
        $pdo->method('prepare')->willReturn($stmt);

        $paginator = new SqlPaginator($pdo);
        $paginator->setSql('SELECT 1');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Params not set');
        $paginator->getItems();
    }
}
