<?php
/**
 * Test Repository class.
 */
namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Repository as BaseRepository;

/**
 * Test Repository class.
 */
class ValidRepository extends BaseRepository
{
	protected static string $_dbName = 'testdb';
	protected static string $_tableName = 'users';

	public function getSetWithMinAge(int $minAge): ValidSet
	{
		return $this->getSetWithParams(['age' => $minAge], ['age' => self::GREATER_OR_EQUALS]);
	}
}
