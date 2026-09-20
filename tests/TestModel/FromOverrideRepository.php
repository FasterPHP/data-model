<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Repository;

/**
 * Test fixture overriding only the from clause hook, to add a join.
 */
class FromOverrideRepository extends Repository
{
    protected const DB_NAME = 'testdb';
    protected const TABLE_NAME = 'users';

    protected function getFromClause(): string
    {
        return '`users` JOIN `accounts` ON `accounts`.`userId` = `users`.`userId`';
    }
}
