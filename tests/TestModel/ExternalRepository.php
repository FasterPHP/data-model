<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Repository;

/**
 * Test fixture for FIELDS_EXTERNAL testing.
 */
class ExternalRepository extends Repository
{
    protected const DB_NAME = 'test';
    protected const TABLE_NAME = 'employees';
    
    /**
     * Override to add department name from joined table
     */
    protected function getSelectClause(): string
    {
        return parent::getSelectClause() . ', `departments`.`name` AS departmentName';
    }
    
    /**
     * Override to add LEFT JOIN with departments table
     */
    protected function getFromClause(): string
    {
        return parent::getFromClause()
            . " LEFT JOIN `departments` ON `departments`.`deptId` = `employees`.`departmentId`";
    }
}
