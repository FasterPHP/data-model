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
     * Characterisation: a single-item lookup on a bare repository currently fetches a whole page.
     */
    public function testGetItemWithParamsCurrentlyFetchesAPage(): void
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

        $this->assertStringEndsWith(' LIMIT 15', $capturedSql);
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
