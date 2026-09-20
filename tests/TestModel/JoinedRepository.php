<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Repository;

/**
 * Test fixture reproducing the shape of a typical joined repository: both
 * getSelectClause() and getFromClause() overridden together to express a join.
 */
class JoinedRepository extends Repository
{
    protected const DB_NAME = 'testdb';
    protected const TABLE_NAME = 'moduleAttempts';

    protected function getSelectClause(): string
    {
        return "{$this->getFieldList()},
				ROUND(100 * moduleAttempts.currentTime / m.duration) AS percentWatched,
				m.name AS moduleName,
				CONCAT(TRIM(mu.firstName), ' ', TRIM(mu.lastName)) AS markedByUserName";
    }

    protected function getFromClause(): string
    {
        return "moduleAttempts
			JOIN modules m ON m.moduleId = moduleAttempts.moduleId
			JOIN courseAttempts ca ON ca.courseAttemptId = moduleAttempts.courseAttemptId
			LEFT JOIN users mu ON mu.userId = moduleAttempts.markedByUserId";
    }
}
