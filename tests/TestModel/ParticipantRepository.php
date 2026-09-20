<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Repository;

/**
 * Test fixture reproducing the shape of a typical course participant repository: the
 * select and from clause hooks overridden together to express a join, plus getWhereSqlAndParams()
 * overridden to inject filters the caller did not supply.
 */
class ParticipantRepository extends Repository
{
    protected const DB_NAME = 'testdb';
    protected const TABLE_NAME = 'users';

    protected function getSelectClause(): string
    {
        return "{$this->getFieldList()},
				CASE
					WHEN a.status IS NULL THEN 'not started'
					ELSE a.status
				END AS courseStatus";
    }

    protected function getFromClause(): string
    {
        return "users
			LEFT JOIN courseAttempts a ON a.userId = users.userId
			LEFT JOIN courseAttempts a2 ON a2.userId = users.userId
				AND a2.courseId = a.courseId
				AND a2.courseAttemptId > a.courseAttemptId";
    }

    protected function getWhereSqlAndParams(array $params, array $types = []): array
    {
        return parent::getWhereSqlAndParams(array_merge($params, [
            'role' => 'staff',
            'status' => 'active',
            'a2.courseAttemptId' => null,
        ]), $types);
    }
}
