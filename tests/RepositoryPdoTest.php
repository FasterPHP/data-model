<?php

/**
 * Tests for Data Model Repository class using PDO.
 */

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\Paginator\Base as PaginatorBase;
use FasterPhp\DataModel\Paginator\SqlPaginator;
use PDO;
use PDOStatement;

/**
 * Tests for Data Model Repository class.
 */
class RepositoryPdoTest extends RepositoryBase
{
    protected function tearDown(): void
    {
        PaginatorBase::setDefaultMaxItemsPerPage(null);
    }

    /**
     * Fetch a Set of everything and return the SQL the repository executed.
     *
     * @param array<int, array<string, string>> $data Rows the mocked statement returns.
     */
    private function captureSetOfAllSql(?Sort $sort = null, ?array $data = null): string
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($data ?? self::$data);

        $capturedSql = '';
        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $mockDbStatement) {
                $capturedSql = $sql;
                return $mockDbStatement;
            });

        (new TestModel\ValidRepository($mockDb, $sort))->getSetOfAll();

        return $capturedSql;
    }

    /**
     * A static default in force does not put a LIMIT on a repository the caller never paginated.
     */
    public function testBareRepositorySqlHasNoLimit(): void
    {
        PaginatorBase::setDefaultMaxItemsPerPage(15);

        $this->assertStringNotContainsString('LIMIT', $this->captureSetOfAllSql());
    }

    /**
     * A Sort still reaches the SQL, but the static default still does not.
     */
    public function testSortedRepositorySqlHasOrderByWithoutLimit(): void
    {
        PaginatorBase::setDefaultMaxItemsPerPage(15);

        $sql = $this->captureSetOfAllSql(new Sort('users.name'));

        $this->assertStringContainsString('ORDER BY `users`.`name` ASC', $sql);
        $this->assertStringNotContainsString('LIMIT', $sql);
    }

    /**
     * A Set from a bare repository holds every matching row, not the first page of them.
     */
    public function testBareRepositorySetIsNotTruncatedToStaticDefault(): void
    {
        PaginatorBase::setDefaultMaxItemsPerPage(15);

        $data = [];
        for ($id = 1; $id <= 20; $id++) {
            $data[] = [
                'id'       => (string) $id,
                'name'     => 'User ' . $id,
                'age'      => '30',
                'height'   => '6.00',
                'handsome' => 'y',
            ];
        }

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($data);

        $capturedSql = '';
        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $mockDbStatement) {
                $capturedSql = $sql;
                return $mockDbStatement;
            });

        $set = (new TestModel\ValidRepository($mockDb))->getSetOfAll();

        $this->assertStringNotContainsString('LIMIT', $capturedSql);
        $this->assertCount(20, $set);
    }

    /**
     * Fetch a Set of everything through a caller-configured repository and return the SQL executed.
     */
    private function captureConfiguredSetOfAllSql(callable $configure): string
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn(self::$data);

        $capturedSql = '';
        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $mockDbStatement) {
                $capturedSql = $sql;
                return $mockDbStatement;
            });

        $repo = new TestModel\ValidRepository($mockDb);
        $configure($repo);
        $repo->getSetOfAll();

        return $capturedSql;
    }

    /**
     * A page size set after construction limits the query.
     */
    public function testPageSizeSetAfterConstructionLimitsTheQuery(): void
    {
        $sql = $this->captureConfiguredSetOfAllSql(
            fn(TestModel\ValidRepository $repo) => $repo->setMaxItemsPerPage(2)
        );

        $this->assertStringEndsWith(' LIMIT 2', $sql);
    }

    /**
     * A sort set after construction orders the query and leaves it unlimited.
     */
    public function testSortSetAfterConstructionOrdersTheQueryWithoutLimiting(): void
    {
        PaginatorBase::setDefaultMaxItemsPerPage(15);

        $sql = $this->captureConfiguredSetOfAllSql(
            fn(TestModel\ValidRepository $repo) => $repo->setSort(new Sort('users.age', Sort::DESCENDING))
        );

        $this->assertStringContainsString('ORDER BY `users`.`age` DESC', $sql);
        $this->assertStringNotContainsString('LIMIT', $sql);
    }

    /**
     * A single-item lookup fetches one row, whatever the static default says.
     */
    public function testGetItemWithParamsFetchesOneRow(): void
    {
        PaginatorBase::setDefaultMaxItemsPerPage(15);

        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([self::$data[0]]);

        $capturedSql = null;
        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $mockDbStatement) {
                $capturedSql = $sql;
                return $mockDbStatement;
            });

        $repo = new TestModel\ValidRepository($mockDb);
        $repo->getItemWithParams(['name' => 'Marcus Don']);

        $this->assertStringEndsWith(' LIMIT 1', $capturedSql);
    }

    protected function getMockDbStatement(): PDOStatement
    {
        return $this->getMockBuilder(PDOStatement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'fetch', 'fetchAll', 'fetchColumn'])
            ->getMock();
    }

    protected function getMockDb(): PDO
    {
        $mockDb = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'exec', 'quote', 'lastInsertId', 'query'])
            ->getMock();

        $mockDb->expects($this->any())
            ->method('quote')
            ->willReturnCallback(function ($value) {
                return "'" . $value . "'";
            });

        $mockDb->expects($this->any())
            ->method('lastInsertId')
            ->willReturnCallback(function () {
                return (string) rand(10, 999);
            });

        return $mockDb;
    }

    /**
     * An explicit page size does not widen a single-item lookup beyond one row.
     */
    public function testGetItemWithParamsFetchesOneRowDespiteExplicitPageSize(): void
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([self::$data[0]]);

        $capturedSql = '';
        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $mockDbStatement) {
                $capturedSql = $sql;
                return $mockDbStatement;
            });

        $paginator = (new SqlPaginator($mockDb))->setMaxItemsPerPage(5);
        (new TestModel\ValidRepository($mockDb, $paginator))->getItemWithParams(['name' => 'Marcus Don']);

        $this->assertStringEndsWith(' LIMIT 1', $capturedSql);
    }

    /**
     * A lookup on a sorted repository orders by that sort and returns the first row under it.
     */
    public function testGetItemWithParamsHonoursRepositorySort(): void
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([self::$data[1]]);

        $capturedSql = '';
        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $mockDbStatement) {
                $capturedSql = $sql;
                return $mockDbStatement;
            });

        $repo = new TestModel\ValidRepository($mockDb, new Sort('users.age', Sort::DESCENDING));
        $item = $repo->getItemWithParams(['handsome' => 'y']);

        $this->assertStringContainsString('ORDER BY `users`.`age` DESC', $capturedSql);
        $this->assertStringEndsWith(' LIMIT 1', $capturedSql);
        $this->assertInstanceOf(TestModel\ValidItem::class, $item);
        $this->assertSame(2, $item->getId());
    }

    /**
     * A lookup matching nothing returns null rather than an Item.
     */
    public function testGetItemWithParamsReturnsNullWhenNothingMatches(): void
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([]);

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($mockDbStatement);

        $repo = new TestModel\ValidRepository($mockDb);

        $this->assertNull($repo->getItemWithParams(['name' => 'Nobody']));
    }

    /**
     * A lookup borrows nothing from the repository's paginator: its figures and cache survive.
     */
    public function testSingleItemLookupLeavesPaginatorFiguresIntact(): void
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->exactly(3))
            ->method('execute')
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(3);
        $mockDbStatement->expects($this->exactly(2))
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturnOnConsecutiveCalls(
                [self::$data[0], self::$data[1]],
                [self::$data[2]],
            );

        // Exactly three statements: the Set fetch, its count, and the lookup. A fourth would mean
        // the lookup had discarded something the paginator had already cached.
        $mockDb = $this->getMockDb();
        $mockDb->expects($this->exactly(3))
            ->method('prepare')
            ->willReturn($mockDbStatement);

        $paginator = (new SqlPaginator($mockDb))->setMaxItemsPerPage(2);
        $repo = new TestModel\ValidRepository($mockDb, $paginator);

        $repo->getSetOfAll();
        $this->assertSame(3, $paginator->getNumItemsTotal());
        $this->assertSame(2, $paginator->getNumPages());

        $repo->getItemWithParams(['name' => 'Jane Doe']);

        $this->assertSame(2, $paginator->getMaxItemsPerPage());
        $this->assertSame(1, $paginator->getPageNum());
        $this->assertSame(3, $paginator->getNumItemsTotal());
        $this->assertSame(2, $paginator->getNumPages());
        $this->assertCount(2, $paginator->getItems());
    }

    /**
     * A lookup that throws leaves no limit behind on the repository's paginator.
     */
    public function testFailingSingleItemLookupLeavesPageSizeUnchanged(): void
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willThrowException(new \PDOException('Query failed'));

        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($mockDbStatement);

        $paginator = (new SqlPaginator($mockDb))->setMaxItemsPerPage(5);
        $repo = new TestModel\ValidRepository($mockDb, $paginator);

        try {
            $repo->getItemWithParams(['name' => 'Marcus Don']);
            $this->fail('Expected the lookup to propagate the PDOException');
        } catch (\PDOException) {
            // Expected.
        }

        $this->assertSame(5, $paginator->getMaxItemsPerPage());
    }

    /**
     * getItemWithId() inherits the one-row limit by delegating, with no code path of its own.
     */
    public function testGetItemWithIdInheritsTheOneRowLimit(): void
    {
        $mockDbStatement = $this->getMockDbStatement();
        $mockDbStatement->expects($this->once())
            ->method('execute')
            ->willReturn(true);
        $mockDbStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([self::$data[0]]);

        $capturedSql = '';
        $mockDb = $this->getMockDb();
        $mockDb->expects($this->once())
            ->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql, $mockDbStatement) {
                $capturedSql = $sql;
                return $mockDbStatement;
            });

        (new TestModel\ValidRepository($mockDb))->getItemWithId(1);

        $this->assertStringEndsWith(' LIMIT 1', $capturedSql);
    }
}
