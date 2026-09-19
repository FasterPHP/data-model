<?php

/**
 * Tests for Data Model Repository class using PDO.
 */

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\Paginator\Base as PaginatorBase;
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
     * A single-item lookup on a bare repository is currently unbounded.
     *
     * Superseded by the LIMIT 1 assertion once single-item lookups gain their own paginator.
     */
    public function testGetItemWithParamsCurrentlyFetchesEverything(): void
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

        $this->assertStringNotContainsString('LIMIT', $capturedSql);
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
}
