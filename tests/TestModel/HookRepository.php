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

    /** @var list<list<mixed>> Arguments of each call to the query hook. */
    public array $hookCalls = [];

    /** @var list<SqlQuery> */
    public array $hookResults = [];

    protected function buildSelectQuery(): SqlQuery
    {
        $this->hookCalls[] = func_get_args();

        $query = parent::buildSelectQuery();
        $this->hookResults[] = $query;

        return $query;
    }
}
