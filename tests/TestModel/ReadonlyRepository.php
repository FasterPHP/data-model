<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Repository;

/**
 * Test fixture for FIELDS_READONLY testing.
 */
class ReadonlyRepository extends Repository
{
    protected const DB_NAME = 'test';
    protected const TABLE_NAME = 'readonly_users';
}
