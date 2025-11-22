<?php

/**
 * Example 01: Basic CRUD Operations
 * 
 * This example demonstrates:
 * - Defining Item, Set, and Repository classes
 * - Creating, reading, updating, and deleting items
 * - Basic validation
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Set;
use FasterPhp\DataModel\Repository;
use FasterPhp\DataModel\Field;

// Define your Item class
class UserItem extends Item
{
    public const DB_NAME = 'example';
    public const TABLE_NAME = 'users';
    
    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
        'age' => Field\Integer::class,
    ];
    
    public const VALIDATORS = [
        'name' => [
            ['class' => \Laminas\Validator\StringLength::class, 'options' => ['min' => 2, 'max' => 100]],
        ],
        'email' => [
            ['class' => \Laminas\Validator\EmailAddress::class],
        ],
    ];
    
    public function getId(): ?int
    {
        return $this->getField('id')->getValue();
    }
    
    public function setId(?int $value): static
    {
        $this->getField('id')->setValue($value);
        return $this;
    }
    
    public function getName(): ?string
    {
        return $this->getField('name')->getValue();
    }
    
    public function setName(?string $value): static
    {
        $this->getField('name')->setValue($value);
        return $this;
    }
    
    public function getEmail(): ?string
    {
        return $this->getField('email')->getValue();
    }
    
    public function setEmail(?string $value): static
    {
        $this->getField('email')->setValue($value);
        return $this;
    }
    
    public function getAge(): ?int
    {
        return $this->getField('age')->getValue();
    }
    
    public function setAge(?int $value): static
    {
        $this->getField('age')->setValue($value);
        return $this;
    }
}

// Define your Set class
class UserSet extends Set
{
    protected string $itemClassName = UserItem::class;
}

// Define your Repository class
class UserRepository extends Repository
{
    protected string $itemClassName = UserItem::class;
    protected string $setClassName = UserSet::class;
}

// Example usage
echo "=== FasterPHP Data Model - Basic Usage Example ===\n\n";

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

// Insert some test data
$pdo->exec("
    INSERT INTO users (name, email, age) VALUES 
    ('Alice Smith', 'alice@example.com', 30),
    ('Bob Jones', 'bob@example.com', 25),
    ('Charlie Brown', 'charlie@example.com', 35)
");

echo "   ✓ Database setup complete\n\n";

// 2. Create repository
echo "2. Creating repository...\n";
$repo = new UserRepository($pdo);
echo "   ✓ Repository created\n\n";

// 3. Fetch a single user by ID
echo "3. Fetching user with ID 1...\n";
$user = $repo->getItemWithId(1);
echo "   Name: {$user->getName()}\n";
echo "   Email: {$user->getEmail()}\n";
echo "   Age: {$user->getAge()}\n\n";

// 4. Fetch all users
echo "4. Fetching all users...\n";
$users = $repo->getSetOfAll();
echo "   Found " . count($users) . " users:\n";
foreach ($users as $user) {
    echo "   - {$user->getName()} ({$user->getEmail()})\n";
}
echo "\n";

// 5. Fetch users with conditions
echo "5. Fetching users older than 28...\n";
$olderUsers = $repo->getSetWithParams(['age' => ['>' => 28]]);
echo "   Found " . count($olderUsers) . " users:\n";
foreach ($olderUsers as $user) {
    echo "   - {$user->getName()}, age {$user->getAge()}\n";
}
echo "\n";

// 6. Create a new user with validation
echo "6. Creating a new user...\n";
$newUser = new UserItem();
$newUser->setName('David Wilson');
$newUser->setEmail('david@example.com');
$newUser->setAge(28);

if ($newUser->isValid()) {
    $repo->saveItem($newUser);
    echo "   ✓ User created with ID: {$newUser->getId()}\n";
} else {
    echo "   ✗ Validation failed:\n";
    foreach ($newUser->getValidationErrors() as $field => $errors) {
        echo "     {$field}: " . implode(', ', $errors) . "\n";
    }
}
echo "\n";

// 7. Update an existing user
echo "7. Updating user with ID 2...\n";
$user = $repo->getItemWithId(2);
$oldName = $user->getName();
$user->setName('Robert Jones');
$repo->saveItem($user);
echo "   ✓ Updated name from '{$oldName}' to '{$user->getName()}'\n\n";

// 8. Validation example (invalid data)
echo "8. Testing validation with invalid data...\n";
$invalidUser = new UserItem();
$invalidUser->setName('X'); // Too short
$invalidUser->setEmail('not-an-email'); // Invalid email

if (!$invalidUser->isValid()) {
    echo "   ✗ Validation failed as expected:\n";
    foreach ($invalidUser->getValidationErrors() as $field => $errors) {
        echo "     {$field}:\n";
        foreach ($errors as $error) {
            echo "       - {$error}\n";
        }
    }
}
echo "\n";

// 9. Delete a user
echo "9. Deleting user with ID 3...\n";
$user = $repo->getItemWithId(3);
$userName = $user->getName();
$user->setToDelete();
$repo->saveItem($user);
echo "   ✓ Deleted user: {$userName}\n\n";

// 10. Verify deletion
echo "10. Verifying deletion...\n";
$users = $repo->getSetOfAll();
echo "   Remaining users: " . count($users) . "\n";
foreach ($users as $user) {
    echo "   - {$user->getName()}\n";
}

echo "\n=== Example Complete ===\n";
