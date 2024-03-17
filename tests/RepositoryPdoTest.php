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
	protected function _getMockDbStatement(): PDOStatement
	{
		return $this->getMockBuilder(PDOStatement::class)
			->disableOriginalConstructor()
			->setMethods(['execute', 'fetch', 'fetchAll', 'fetchColumn'])
			->getMock();
	}

	protected function _getMockDb(): PDO
	{
		$mockDb = $this->getMockBuilder(PDO::class)
			->disableOriginalConstructor()
			->setMethods(['getDbKey', 'prepare', 'exec', 'quote', 'lastInsertId', 'query'])
			->getMock();

		$mockDb->expects($this->any())
			->method('quote')
			->will($this->returnCallback(function($value) {
                return "'" . $value . "'";
            }));

		$mockDb->expects($this->any())
			->method('lastInsertId')
			->will($this->returnCallback(function() {
                return (string) rand(10, 999);
            }));

		return $mockDb;
	}
}
