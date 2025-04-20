<?php

/**
 * Tests for Data Model Repository class using FasterPhp\Db.
 */

namespace FasterPhp\DataModel;

use PDO;
use FasterPhp\Db\Db;
use FasterPhp\Db\DbStatement;

/**
 * Tests for Data Model Repository class.
 */
class RepositoryDbTest extends RepositoryBase
{
    protected function getMockDbStatement(): DbStatement
    {
        return $this->getMockBuilder(DbStatement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'fetch', 'fetchAll', 'fetchColumn'])
            ->getMock();
    }

    protected function getMockDb(): Db
    {
        $mockPdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['quote', 'lastInsertId'])
            ->getMock();
        $mockPdo->expects($this->any())
            ->method('quote')
            ->willReturnCallback(function ($value) {
                return "'" . $value . "'";
            });
        $mockPdo->expects($this->any())
            ->method('lastInsertId')
            ->willReturnCallback(function () {
                return (string) rand(10, 999);
            });

        $mockDb = $this->getMockBuilder(Db::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'exec', 'getPdo', 'query'])
            ->getMock();
        $mockDb->expects($this->any())
            ->method('getPdo')
            ->willReturn($mockPdo);

        return $mockDb;
    }
}
