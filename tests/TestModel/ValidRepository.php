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
    protected const DB_NAME = 'testdb';
    protected const TABLE_NAME = 'users';

    public function getSetWithMinAge(int $minAge): ValidSet
    {
        return $this->getSetWithParams(['age' => $minAge], ['age' => self::GREATER_OR_EQUALS]);
    }
}
