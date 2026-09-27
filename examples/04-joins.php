<?php

/**
 * Example 04: Complex Queries with Joins
 *
 * This example demonstrates:
 * - Extending Repository to add JOIN clauses
 * - Adding read-only fields from joined tables
 * - Avoiding N+1 query problems
 * - Overriding getSelectClause() and getFromClause()
 * - Replacing the whole query with a hand-written SqlQuery
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Set;
use FasterPhp\DataModel\Repository;
use FasterPhp\DataModel\Sort;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Sql\SqlFragment;
use FasterPhp\DataModel\Sql\SqlQuery;

// Define Department Item
class DepartmentItem extends Item
{
    public const ID_FIELD = 'deptId';

    public const FIELDS = [
        'name' => Field\Varchar::class,
    ];
}

class DepartmentSet extends Set
{
}

class DepartmentRepository extends Repository
{
    protected const DB_NAME = 'example';
    protected const TABLE_NAME = 'departments';
}

// Define Employee Item with external, read-only department name field
class EmployeeItem extends Item
{
    public const ID_FIELD = 'empId';

    public const FIELDS = [
        'name' => Field\Varchar::class,
        'departmentId' => Field\Integer::class,
        'salary' => Field\Integer::class,
    ];

    public const FIELDS_EXTERNAL = [
        'departmentName' => Field\Varchar::class,
    ];
}

class EmployeeSet extends Set
{
}

// Extended Repository with JOIN
class EmployeeRepository extends Repository
{
    protected const DB_NAME = 'example';
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

// Define an Item for a query the clause hooks cannot express
class HighEarnerItem extends Item
{
    public const ID_FIELD = 'empId';

    public const FIELDS = [
        'name' => Field\Varchar::class,
        'salary' => Field\Integer::class,
    ];

    public const FIELDS_EXTERNAL = [
        'departmentName' => Field\Varchar::class,
        'departmentAverage' => Field\Integer::class,
    ];
}

class HighEarnerSet extends Set
{
}

/**
 * Repository returning a hand-written query from the coarse extension point.
 *
 * buildSelectQuery() returns the base query every Set and Item retrieval starts from. Its default
 * implementation composes the clause hooks, as EmployeeRepository above relies on. Returning a
 * query built here instead replaces that composition wholesale, which is the supported way to
 * express a query the clause hooks cannot: here, a derived table binding a parameter of its own.
 * Unlike escaping to PDO directly, the query still receives the repository's filtering, sorting,
 * pagination and Item construction.
 *
 * The hook takes no arguments, so the query is the same for every call. Anything that varies per
 * call, such as a salary threshold, is a filter passed to getSetWithParams(), which the repository
 * ANDs onto the query's WHERE clause. Filters qualify declared fields with the table name, so the
 * base table appears as `employees`, not an alias.
 */
class HighEarnerRepository extends Repository
{
    protected const DB_NAME = 'example';
    protected const TABLE_NAME = 'employees';

    protected function buildSelectQuery(): SqlQuery
    {
        return new SqlQuery(
            // The id column is aliased to Item::ID_INTERNAL, exactly as getFieldList() does
            select: new SqlFragment(
                '`employees`.`empId` AS `id`, `employees`.`name`, `employees`.`salary`'
                . ', d.name AS departmentName'
                . ', avg.departmentAverage AS departmentAverage'
            ),
            // The department average counts permanent staff only; the placeholder is prefixed so
            // that it cannot collide with a filter on a field
            from: new SqlFragment(
                '`employees`'
                . ' JOIN departments d ON d.deptId = `employees`.`departmentId`'
                . ' JOIN ('
                . '   SELECT departmentId, CAST(AVG(salary) AS INTEGER) AS departmentAverage'
                . '   FROM employees WHERE contract = :q_contract GROUP BY departmentId'
                . ' ) avg ON avg.departmentId = `employees`.`departmentId`',
                [':q_contract' => 'permanent'],
            ),
        );
    }
}

// Example usage
echo "=== FasterPHP Data Model - Joins Example ===\n\n";

// 1. Setup database connection
echo "1. Setting up database connection...\n";
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables
$pdo->exec("
    CREATE TABLE departments (
        deptId INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100)
    )
");

$pdo->exec("
    CREATE TABLE employees (
        empId INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100),
        departmentId INTEGER,
        salary INTEGER,
        contract VARCHAR(20) NOT NULL DEFAULT 'permanent'
    )
");

// Insert departments
$pdo->exec("
    INSERT INTO departments (name) VALUES
    ('Engineering'),
    ('Sales'),
    ('Marketing'),
    ('HR')
");

// Insert employees
$pdo->exec("
    INSERT INTO employees (name, departmentId, salary) VALUES
    ('Alice Smith', 1, 90000),
    ('Bob Jones', 1, 85000),
    ('Charlie Brown', 2, 75000),
    ('David Wilson', 2, 80000),
    ('Eve Davis', 3, 70000),
    ('Frank Miller', 3, 72000),
    ('Grace Lee', 4, 65000),
    ('Henry Taylor', 1, 95000)
");
$pdo->exec("UPDATE employees SET contract = 'contractor' WHERE name = 'Henry Taylor'");

echo "   ✓ Database setup complete\n\n";

// 2. Fetch employees WITH join (efficient)
echo "2. Fetching employees WITH join (single query)...\n";
$repo = new EmployeeRepository($pdo);
$employees = $repo->getSetOfAll();

echo "   Found " . count($employees) . " employees (single query):\n";
foreach ($employees as $employee) {
    echo "   - {$employee->getName()} ({$employee->getDepartmentName()}) - \${$employee->getSalary()}\n";
}
echo "\n";

// 3. Filter by department name using joined data
echo "3. Fetching Engineering employees...\n";
$engineers = $repo->getSetWithParams(['departments.name' => 'Engineering']);

echo "   Found " . count($engineers) . " engineers:\n";
foreach ($engineers as $employee) {
    echo "   - {$employee->getName()} ({$employee->getDepartmentName()}) - \${$employee->getSalary()}\n";
}
echo "\n";

// 4. Replace the whole query with one the clause hooks cannot express
echo "4. Fetching high earners with their department's permanent-staff average (hand-written query)...\n";
$highEarners = (new HighEarnerRepository($pdo))
    ->setSort(new Sort('salary', Sort::DESCENDING))
    ->getSetWithParams(['salary' => 80000], ['salary' => Repository::GREATER_OR_EQUALS]);

echo "   The salary threshold is a filter, ANDed onto the hand-written query by the repository.\n";
echo "   Found " . count($highEarners) . " employees earning at least \$80,000:\n";
foreach ($highEarners as $employee) {
    echo "   - {$employee->getName()} ({$employee->getDepartmentName()})"
        . " - \${$employee->getSalary()}"
        . " vs permanent-staff average \${$employee->getDepartmentAverage()}\n";
}

echo "\n=== Example Complete ===\n";
