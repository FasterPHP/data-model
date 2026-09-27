<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Repository;
use FasterPhp\DataModel\Sql\SqlQuery;

/**
 * Test fixture returning a query it constructed itself from the query hook, in place of the
 * default composition of the clause hooks.
 */
class HandWrittenRepository extends Repository
{
    protected const DB_NAME = 'testdb';
    protected const TABLE_NAME = 'users';

    private ?SqlQuery $handWrittenQuery = null;

    /** Number of times the select clause hook was consulted. */
    public int $selectClauseCalls = 0;

    /** Number of times the from clause hook was consulted. */
    public int $fromClauseCalls = 0;

    public function setHandWrittenQuery(SqlQuery $query): static
    {
        $this->handWrittenQuery = $query;
        return $this;
    }

    protected function getSelectClause(): string
    {
        $this->selectClauseCalls++;
        return parent::getSelectClause();
    }

    protected function getFromClause(): string
    {
        $this->fromClauseCalls++;
        return parent::getFromClause();
    }

    protected function buildSelectQuery(): SqlQuery
    {
        return $this->handWrittenQuery ?? parent::buildSelectQuery();
    }
}
