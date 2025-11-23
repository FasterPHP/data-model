<?php

/**
 * Example 05: Batch Updates and Deletes
 * 
 * This example demonstrates:
 * - Fetching a Set and updating multiple items
 * - Using saveSet() for efficient batch operations
 * - Marking items for deletion with setToDelete()
 * - Batch deleting multiple items in a single transaction
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
    public const ID_FIELD = 'id';
    
    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
        'active' => Field\Boolean::class,
        'loginCount' => Field\Integer::class,
    ];
    
    public function getId(): ?int
    {
        return $this->getField('id')->getValue();
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
    
    public function getActive(): ?bool
    {
        return $this->getField('active')->getValue();
    }
    
    public function setActive(?bool $value): static
    {
        $this->getField('active')->setValue($value);
        return $this;
    }
    
    public function getLoginCount(): ?int
    {
        return $this->getField('loginCount')->getValue();
    }
    
    public function setLoginCount(?int $value): static
    {
        $this->getField('loginCount')->setValue($value);
        return $this;
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
echo "=== FasterPHP Data Model - Batch Operations Example ===\n\n";

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
        active INTEGER DEFAULT 1,
        loginCount INTEGER DEFAULT 0
    )
");

// Insert test data
$pdo->exec("
    INSERT INTO users (name, email, active, loginCount) VALUES 
    ('Alice Smith', 'alice@example.com', 1, 5),
    ('Bob Jones', 'bob@example.com', 0, 2),
    ('Charlie Brown', 'charlie@example.com', 1, 10),
    ('David Wilson', 'david@example.com', 0, 0),
    ('Eve Davis', 'eve@example.com', 1, 15),
    ('Frank Miller', 'frank@example.com', 0, 1),
    ('Grace Lee', 'grace@example.com', 1, 8)
");

echo "   ✓ Database setup complete (7 users)\n\n";

$repo = new UserRepository($pdo);

// 2. Display initial state
echo "2. Initial user state...\n";
$users = $repo->getSetOfAll();
foreach ($users as $user) {
    $status = $user->getActive() ? 'active' : 'inactive';
    echo "   - {$user->getName()} ({$status}, {$user->getLoginCount()} logins)\n";
}
echo "\n";

// 3. Batch update: Activate all inactive users
echo "3. Batch update: Activating all inactive users...\n";
$inactiveUsers = $repo->getSetWithParams(['active' => false]);
echo "   Found " . count($inactiveUsers) . " inactive users\n";

foreach ($inactiveUsers as $user) {
    echo "   - Activating: {$user->getName()}\n";
    $user->setActive(true);
}

$repo->saveSet($inactiveUsers);
echo "   ✓ Batch update complete (single transaction)\n\n";

// 4. Verify the update
echo "4. Verifying update...\n";
$users = $repo->getSetOfAll();
$activeCount = 0;
foreach ($users as $user) {
    if ($user->getActive()) {
        $activeCount++;
    }
}
echo "   Active users: {$activeCount} / " . count($users) . "\n\n";

// 5. Batch update: Increment login count for active users
echo "5. Batch update: Incrementing login count for all users...\n";
$users = $repo->getSetOfAll();
foreach ($users as $user) {
    $oldCount = $user->getLoginCount();
    $newCount = $oldCount + 1;
    $user->setLoginCount($newCount);
    echo "   - {$user->getName()}: {$oldCount} → {$newCount} logins\n";
}

$repo->saveSet($users);
echo "   ✓ Batch update complete\n\n";

// 6. Batch delete: Remove users with low login counts
echo "6. Batch delete: Removing users with login count < 3...\n";
$users = $repo->getSetWithParams(
    ['loginCount' => 3],
    ['loginCount' => Repository::LESS]
);

echo "   Found " . count($users) . " users to delete:\n";
foreach ($users as $user) {
    echo "   - {$user->getName()} ({$user->getLoginCount()} logins)\n";
    $user->setToDelete();
}

$repo->saveSet($users);
echo "   ✓ Batch delete complete\n\n";

// 7. Verify deletion
echo "7. Verifying deletion...\n";
$users = $repo->getSetOfAll();
echo "   Remaining users: " . count($users) . "\n";
foreach ($users as $user) {
    echo "   - {$user->getName()} ({$user->getLoginCount()} logins)\n";
}
echo "\n";

// 8. Mixed batch operation: Update some, delete others
echo "8. Mixed batch operation: Update high performers, delete low performers...\n";
$users = $repo->getSetOfAll();

foreach ($users as $user) {
    $loginCount = $user->getLoginCount();
    
    if ($loginCount >= 10) {
        // High performers: Give them a bonus (represented by incrementing login count)
        $user->setLoginCount($loginCount + 5);
        echo "   - Bonus for {$user->getName()}: {$loginCount} → " . ($loginCount + 5) . " logins\n";
    } elseif ($loginCount < 5) {
        // Low performers: Mark for deletion
        $user->setToDelete();
        echo "   - Removing {$user->getName()} ({$loginCount} logins)\n";
    }
}

$repo->saveSet($users);
echo "   ✓ Mixed batch operation complete\n\n";

// 9. Final state
echo "9. Final user state...\n";
$users = $repo->getSetOfAll();
echo "   Remaining users: " . count($users) . "\n";
foreach ($users as $user) {
    echo "   - {$user->getName()} ({$user->getLoginCount()} logins)\n";
}

echo "\n=== Example Complete ===\n";
