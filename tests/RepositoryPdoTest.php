<?php

/**
 * Tests for Data Model Repository class using PDO.
 */

namespace FasterPhp\DataModel;

use PDO;
use PDOStatement;

/**
 * Tests for Data Model Repository class.
 */
class RepositoryPdoTest extends RepositoryBase
{
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
