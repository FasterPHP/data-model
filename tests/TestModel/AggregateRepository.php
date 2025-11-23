<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Repository;

/**
 * Test fixture for FIELDS_AGGREGATE testing.
 */
class AggregateRepository extends Repository
{
    protected const DB_NAME = 'test';
    protected const TABLE_NAME = 'orders';
    
    /**
     * Override to add aggregate fields
     */
    protected function getSelectClause(): string
    {
        return parent::getSelectClause() 
            . ', SUM(`orders`.`amount`) AS totalAmount'
            . ', COUNT(*) AS orderCount';
    }
}
