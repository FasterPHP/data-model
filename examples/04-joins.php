<?php

/**
 * Example 04: Complex Queries with Joins
 * 
 * This example demonstrates:
 * - Extending Repository to add JOIN clauses
 * - Adding read-only fields from joined tables
 * - Avoiding N+1 query problems
 * - Overriding getSelectClause() and getFromClause()
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Set;
use FasterPhp\DataModel\Repository;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Sql;

// Define Department Item
class DepartmentItem extends Item
{
    public const DB_NAME = 'example';
    public const TABLE_NAME = 'departments';
    public const ID_FIELD = 'id';
    
    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
    ];
    
    public function getId(): ?int
    {
        return $this->getField('id')->getValue();
    }
    
    public function getName(): ?string
    {
        return $this->getField('name')->getValue();
    }
}

class DepartmentSet extends Set
{
}

class DepartmentRepository extends Repository
{
    protected const DB_NAME = 'example';
    protected const TABLE_NAME = 'departments';
}

// Define Employee Item with read-only department name field
class EmployeeItem extends Item
{
    public const DB_NAME = 'example';
    public const TABLE_NAME = 'employees';
    public const ID_FIELD = 'id';
    
    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
        'departmentId' => Field\Integer::class,
        'salary' => Field\Integer::class,
    ];
    
    // Read-only field from joined table (not in FIELDS)
    private ?string $departmentName = null;
    
    public function getId(): ?int
    {
        return $this->getField('id')->getValue();
    }
    
    public function getName(): ?string
    {
        return $this->getField('name')->getValue();
    }
    
    public function getDepartmentId(): ?int
    {
        return $this->getField('departmentId')->getValue();
    }
    
    public function setDepartmentId(?int $value): static
    {
        $this->getField('departmentId')->setValue($value);
        return $this;
    }
    
    public function getSalary(): ?int
    {
        return $this->getField('salary')->getValue();
    }
    
    public function setSalary(?int $value): static
    {
        $this->getField('salary')->setValue($value);
        return $this;
    }
    
    // Getter for read-only joined field
    public function getDepartmentName(): ?string
    {
        return $this->departmentName;
    }
    
    // Setter for read-only joined field (used by Repository)
    public function setDepartmentName(?string $value): static
    {
        $this->departmentName = $value;
        return $this;
    }
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
        $baseFields = parent::getSelectClause();
        $tableName = Sql::ident($this->getTableName());
        $deptName = Sql::ident('departments.name');
        
        return "{$baseFields}, {$deptName} AS departmentName";
    }
    
    /**
     * Override to add LEFT JOIN with departments table
     */
    protected function getFromClause(): string
    {
        $tableName = Sql::ident($this->getTableName());
        $deptTable = Sql::ident('departments');
        
        return "{$tableName} LEFT JOIN {$deptTable} ON {$tableName}.departmentId = {$deptTable}.id";
    }
    
    /**
     * Override to populate the read-only departmentName field
     */
    protected function createItem(array $data = []): Item
    {
        $item = parent::createItem($data);
        
        // Set the read-only field if present in data
        if (isset($data['departmentName'])) {
            $item->setDepartmentName($data['departmentName']);
        }
        
        return $item;
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
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100)
    )
");

$pdo->exec("
    CREATE TABLE employees (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100),
        departmentId INTEGER,
        salary INTEGER
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

echo "   ✓ Database setup complete\n\n";

// 2. Fetch employees WITHOUT join (N+1 problem)
echo "2. Fetching employees WITHOUT join (N+1 problem)...\n";
$basicRepo = new Repository($pdo);
$basicRepo->setItemClassName('EmployeeItem');
$basicRepo->setSetClassName('EmployeeSet');
$employees = $basicRepo->getSetOfAll();

$deptRepo = new DepartmentRepository($pdo);
echo "   This would require 1 query for employees + N queries for departments:\n";
$queryCount = 1;
foreach ($employees as $employee) {
    // In real code, this would be: $dept = $deptRepo->getItemWithId($employee->getDepartmentId());
    // Each iteration = 1 additional query
    $queryCount++;
    if ($queryCount > 3) {
        echo "   ... (total: " . count($employees) . " + 1 queries)\n";
        break;
    }
}
echo "\n";

// 3. Fetch employees WITH join (efficient)
echo "3. Fetching employees WITH join (single query)...\n";
$repo = new EmployeeRepository($pdo);
$employees = $repo->getSetOfAll();

echo "   Found " . count($employees) . " employees (single query):\n";
foreach ($employees as $employee) {
    echo "   - {$employee->getName()} ({$employee->getDepartmentName()}) - \${$employee->getSalary()}\n";
}
echo "\n";

// 4. Filter by department using joined data
echo "4. Fetching Engineering employees...\n";
$engineers = $repo->getSetWithParams(['departmentId' => 1]);

echo "   Found " . count($engineers) . " engineers:\n";
foreach ($engineers as $employee) {
    echo "   - {$employee->getName()} ({$employee->getDepartmentName()}) - \${$employee->getSalary()}\n";
}
echo "\n";

// 5. Filter by salary range
echo "5. Fetching employees earning more than \$80,000...\n";
$highEarners = $repo->getSetWithParams(
    ['salary' => 80000],
    ['salary' => Repository::GREATER]
);

echo "   Found " . count($highEarners) . " high earners:\n";
foreach ($highEarners as $employee) {
    echo "   - {$employee->getName()} ({$employee->getDepartmentName()}) - \${$employee->getSalary()}\n";
}
echo "\n";

// 6. Demonstrate that read-only field is not saved
echo "6. Demonstrating read-only field behavior...\n";
$employee = $repo->getItemWithId(1);
echo "   Original: {$employee->getName()} - {$employee->getDepartmentName()}\n";

// Try to change department name (this won't affect the database)
$employee->setDepartmentName('Modified Department');
echo "   After setDepartmentName: {$employee->getDepartmentName()}\n";

// Save the employee (departmentName is not saved)
$repo->saveItem($employee);

// Fetch again to verify departmentName wasn't saved
$employee = $repo->getItemWithId(1);
echo "   After save and reload: {$employee->getName()} - {$employee->getDepartmentName()}\n";
echo "   (departmentName remains unchanged because it's read-only)\n";

echo "\n=== Example Complete ===\n";
