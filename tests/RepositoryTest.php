<?php
/**
 * Tests for Data Model Repository class.
 */
namespace FasterPhp\DataModel;

use PHPUnit\Framework\TestCase;
use PDO;
use FasterPhp\DataModel\Paginator\SqlPaginator;
use FasterPhp\DataModel\TestModel;

/**
 * Tests for Data Model Repository class.
 */
class RepositoryTest extends TestCase
{
	public function testGetDbNameNotSet(): void
	{
		$repo = new TestModel\NothingSetRepository();

		$this->expectException(\FasterPhp\DataModel\Exception::class);
		$this->expectExceptionMessage('Database name not set');

		$repo->getDbName();
	}

	public function testGetDbName(): void
	{
		$repo = new TestModel\ValidRepository();

		$this->assertSame('testdb', $repo->getDbName());
	}

	public function testGetTableNameNotSet(): void
	{
		$repo = new TestModel\NothingSetRepository();

		$this->expectException(\FasterPhp\DataModel\Exception::class);
		$this->expectExceptionMessage('Table name not set');

		$repo->getTableName();
	}

	public function testGetTableName(): void
	{
		$repo = new TestModel\ValidRepository();

		$this->assertSame('users', $repo->getTableName());
	}

	public function testGetIdField(): void
	{
		$repo = new TestModel\ValidRepository();

		$this->assertSame('userId', $repo->getIdField());
	}
}
