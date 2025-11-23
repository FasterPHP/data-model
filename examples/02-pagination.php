<?php

/**
 * Example 02: Pagination and Sorting
 * 
 * This example demonstrates:
 * - Using SqlPaginator for pagination
 * - Using Sort for ordering results
 * - Accessing pagination metadata (total items, page count)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Set;
use FasterPhp\DataModel\Repository;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Paginator\SqlPaginator;
use FasterPhp\DataModel\Sort;

// Define your Item class
class UserItem extends Item
{
    public const DB_NAME = 'example';
    public const TABLE_NAME = 'users';
    public const ID_FIELD = 'id';
    
    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
        'age' => Field\Integer::class,
    ];
    
    public function getId(): ?int
    {
        return $this->getField('id')->getValue();
    }
    
    public function getName(): ?string
    {
        return $this->getField('name')->getValue();
    }
    
    public function getEmail(): ?string
    {
        return $this->getField('email')->getValue();
    }
    
    public function getAge(): ?int
    {
        return $this->getField('age')->getValue();
    }
}

// Define your Set class
class UserSet extends Set
{
    // No properties needed - class names inferred from naming convention
}

// Define your Repository class
class UserRepository extends Repository
{
    protected const DB_NAME = 'example';
    protected const TABLE_NAME = 'users';
}

// Example usage
echo "=== FasterPHP Data Model - Pagination and Sorting Example ===\n\n";

// 1. Setup database connection
echo "1. Setting up database connection...\n";
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create example table
$pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100),
        email VARCHAR(100),
        age INTEGER
    )
");

// Insert test data (20 users)
$names = ['Alice', 'Bob', 'Charlie', 'David', 'Eve', 'Frank', 'Grace', 'Henry', 'Ivy', 'Jack',
          'Kate', 'Liam', 'Mia', 'Noah', 'Olivia', 'Peter', 'Quinn', 'Rachel', 'Sam', 'Tina'];
foreach ($names as $i => $name) {
    $age = 20 + ($i % 30);
    $pdo->exec("INSERT INTO users (name, email, age) VALUES ('{$name}', '{$name}@example.com', {$age})");
}

echo "   ✓ Database setup complete (20 users)\n\n";

// 2. Basic pagination (page 1, 5 items per page)
echo "2. Fetching page 1 (5 items per page)...\n";
$paginator = new SqlPaginator($pdo);
$paginator->setMaxItemsPerPage(5);
$paginator->setPageNum(1);
$repo = new UserRepository($pdo, $paginator);
$users = $repo->getSetOfAll();

echo "   Page: {$paginator->getPageNum()} of {$paginator->getNumPages()}\n";
echo "   Total items: {$paginator->getNumItemsTotal()}\n";
echo "   Items on this page:\n";
foreach ($users as $user) {
    echo "   - {$user->getName()} (age {$user->getAge()})\n";
}
echo "\n";

// 3. Navigate to page 2
echo "3. Fetching page 2...\n";
$paginator->setPageNum(2);
$users = $repo->getSetOfAll();

echo "   Page: {$paginator->getPageNum()} of {$paginator->getNumPages()}\n";
echo "   Items on this page:\n";
foreach ($users as $user) {
    echo "   - {$user->getName()} (age {$user->getAge()})\n";
}
echo "\n";

// 4. Sorting by name (ascending)
echo "4. Fetching all users sorted by name (ascending)...\n";
$sort = new Sort('name', Sort::ASCENDING);
$repo = new UserRepository($pdo, $sort);
$users = $repo->getSetOfAll();

echo "   First 5 users:\n";
$count = 0;
foreach ($users as $user) {
    echo "   - {$user->getName()}\n";
    if (++$count >= 5) {
        break;
    }
}
echo "\n";

// 5. Sorting by age (descending)
echo "5. Fetching all users sorted by age (descending)...\n";
$sort = new Sort('age', Sort::DESCENDING);
$repo = new UserRepository($pdo, $sort);
$users = $repo->getSetOfAll();

echo "   First 5 users:\n";
$count = 0;
foreach ($users as $user) {
    echo "   - {$user->getName()}, age {$user->getAge()}\n";
    if (++$count >= 5) {
        break;
    }
}
echo "\n";

// 6. Combined: Pagination + Sorting
echo "6. Fetching page 1 (5 items) sorted by age (descending)...\n";
$paginator = new SqlPaginator($pdo);
$paginator->setMaxItemsPerPage(5);
$paginator->setPageNum(1);
$sort = new Sort('age', Sort::DESCENDING);
$repo = new UserRepository($pdo, $paginator);
$repo->setSort($sort);
$users = $repo->getSetOfAll();

echo "   Page: {$paginator->getPageNum()} of {$paginator->getNumPages()}\n";
echo "   Items on this page:\n";
foreach ($users as $user) {
    echo "   - {$user->getName()}, age {$user->getAge()}\n";
}
echo "\n";

// 7. Filtered pagination (users older than 30)
echo "7. Fetching page 1 of users older than 30 (3 items per page)...\n";
$paginator = new SqlPaginator($pdo);
$paginator->setMaxItemsPerPage(3);
$paginator->setPageNum(1);
$repo = new UserRepository($pdo, $paginator);
$users = $repo->getSetWithParams(
    ['age' => 30],
    ['age' => Repository::GREATER]
);

echo "   Page: {$paginator->getPageNum()} of {$paginator->getNumPages()}\n";
echo "   Total matching items: {$paginator->getNumItemsTotal()}\n";
echo "   Items on this page:\n";
foreach ($users as $user) {
    echo "   - {$user->getName()}, age {$user->getAge()}\n";
}

echo "\n=== Example Complete ===\n";
