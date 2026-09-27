<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Repository;
use FasterPhp\DataModel\Sql\SqlQuery;

/**
 * Test fixture recording every call to the query hook, and the query it returned.
 */
class HookRepository extends Repository
{
    protected const DB_NAME = 'testdb';
    protected const TABLE_NAME = 'users';

    /** @var list<array{params: array<string, mixed>, types: array<string, string>}> */
    public array $hookCalls = [];

    /** @var list<SqlQuery> */
    public array $hookResults = [];

    protected function buildSelectQuery(array $params, array $types = []): SqlQuery
    {
        $this->hookCalls[] = ['params' => $params, 'types' => $types];

        $query = parent::buildSelectQuery($params, $types);
        $this->hookResults[] = $query;

        return $query;
    }
}
