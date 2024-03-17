<?php
/**
 * Tests for Data Model Repository class using FasterPhp\Db.
 */
namespace FasterPhp\DataModel;

use PDO;
use FasterPhp\Db\Db;
use FasterPhp\Db\Statement;

/**
 * Tests for Data Model Repository class.
 */
class RepositoryDbTest extends RepositoryBase
{
	protected function _getMockDbStatement(): Statement
	{
		return $this->getMockBuilder(Statement::class)
			->disableOriginalConstructor()
			->setMethods(['execute', 'fetch', 'fetchAll', 'fetchColumn'])
			->getMock();
	}

	protected function _getMockDb(): Db
	{
		$mockPdo = $this->getMockBuilder(PDO::class)
			->disableOriginalConstructor()
			->setMethods(['quote', 'lastInsertId'])
			->getMock();
		$mockPdo->expects($this->any())
			->method('quote')
			->will($this->returnCallback(function($value) {
				return "'" . $value . "'";
			}));
		$mockPdo->expects($this->any())
			->method('lastInsertId')
			->will($this->returnCallback(function() {
                return (string) rand(10, 999);
            }));

		$mockDb = $this->getMockBuilder(Db::class)
			->disableOriginalConstructor()
			->setMethods(['getDbKey', 'prepare', 'exec', 'getPdo', 'query'])
			->getMock();
		$mockDb->expects($this->any())
			->method('getDbKey')
			->willReturn('testdb');
		$mockDb->expects($this->any())
			->method('getPdo')
			->willReturn($mockPdo);

		return $mockDb;
	}
}
