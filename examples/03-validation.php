<?php

/**
 * Example 03: Validation and Error Handling
 *
 * This example demonstrates:
 * - Defining validation rules using VALIDATORS constant
 * - Checking validation status with isValid()
 * - Retrieving validation errors with getValidationErrors()
 * - Multiple validators per field
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Set;
use FasterPhp\DataModel\Repository;
use FasterPhp\DataModel\Field;

// Define your Item class with comprehensive validation
class UserItem extends Item
{
    public const ID_FIELD = 'userId';

    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
        'age' => Field\Integer::class,
        'username' => Field\Varchar::class,
    ];

    public const VALIDATORS = [
        'name' => [
            ['class' => \Laminas\Validator\StringLength::class, 'options' => ['min' => 2, 'max' => 100]],
            ['class' => \Laminas\Validator\NotEmpty::class],
        ],
        'email' => [
            ['class' => \Laminas\Validator\EmailAddress::class],
            ['class' => \Laminas\Validator\NotEmpty::class],
        ],
        'age' => [
            ['class' => \Laminas\Validator\Between::class, 'options' => ['min' => 18, 'max' => 120]],
        ],
        'username' => [
            ['class' => \Laminas\Validator\StringLength::class, 'options' => ['min' => 3, 'max' => 20]],
            ['class' => \Laminas\Validator\Regex::class, 'options' => ['pattern' => '/^[a-zA-Z0-9_]+$/']],
        ],
    ];
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
echo "=== FasterPHP Data Model - Validation Example ===\n\n";

// 1. Setup database connection
echo "1. Setting up database connection...\n";
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create example table
$pdo->exec("
    CREATE TABLE users (
        userId INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100),
        email VARCHAR(100),
        age INTEGER,
        username VARCHAR(20)
    )
");

echo "   ✓ Database setup complete\n\n";

$repo = new UserRepository($pdo);

// 2. Valid user (happy path)
echo "2. Creating a valid user...\n";
$validUser = new UserItem();
$validUser->setName('Alice Smith');
$validUser->setEmail('alice@example.com');
$validUser->setAge(30);
$validUser->setUsername('alice_smith');

if ($validUser->isValid()) {
    $repo->saveItem($validUser);
    echo "   ✓ User created successfully with ID: {$validUser->getId()}\n";
    echo "   Name: {$validUser->getName()}\n";
    echo "   Email: {$validUser->getEmail()}\n";
    echo "   Age: {$validUser->getAge()}\n";
    echo "   Username: {$validUser->getUsername()}\n";
} else {
    echo "   ✗ Validation failed (unexpected)\n";
}
echo "\n";

// 3. Invalid name (too short)
echo "3. Testing validation: name too short...\n";
$invalidUser = new UserItem();
$invalidUser->setName('X');
$invalidUser->setEmail('test@example.com');
$invalidUser->setAge(25);
$invalidUser->setUsername('testuser');

if (!$invalidUser->isValid()) {
    echo "   ✗ Validation failed as expected:\n";
    $errors = $invalidUser->getValidationErrors();
    foreach ($errors as $field => $fieldErrors) {
        echo "   Field '{$field}':\n";
        foreach ($fieldErrors as $error) {
            echo "     - {$error}\n";
        }
    }
}
echo "\n";

// 4. Invalid email
echo "4. Testing validation: invalid email...\n";
$invalidUser = new UserItem();
$invalidUser->setName('Bob Jones');
$invalidUser->setEmail('not-an-email');
$invalidUser->setAge(25);
$invalidUser->setUsername('bobjones');

if (!$invalidUser->isValid()) {
    echo "   ✗ Validation failed as expected:\n";
    $errors = $invalidUser->getValidationErrors();
    foreach ($errors as $field => $fieldErrors) {
        echo "   Field '{$field}':\n";
        foreach ($fieldErrors as $error) {
            echo "     - {$error}\n";
        }
    }
}
echo "\n";

// 5. Invalid age (too young)
echo "5. Testing validation: age too young...\n";
$invalidUser = new UserItem();
$invalidUser->setName('Charlie Brown');
$invalidUser->setEmail('charlie@example.com');
$invalidUser->setAge(15);
$invalidUser->setUsername('charlie');

if (!$invalidUser->isValid()) {
    echo "   ✗ Validation failed as expected:\n";
    $errors = $invalidUser->getValidationErrors();
    foreach ($errors as $field => $fieldErrors) {
        echo "   Field '{$field}':\n";
        foreach ($fieldErrors as $error) {
            echo "     - {$error}\n";
        }
    }
}
echo "\n";

// 6. Invalid username (contains special characters)
echo "6. Testing validation: username with special characters...\n";
$invalidUser = new UserItem();
$invalidUser->setName('David Wilson');
$invalidUser->setEmail('david@example.com');
$invalidUser->setAge(28);
$invalidUser->setUsername('david@wilson!');

if (!$invalidUser->isValid()) {
    echo "   ✗ Validation failed as expected:\n";
    $errors = $invalidUser->getValidationErrors();
    foreach ($errors as $field => $fieldErrors) {
        echo "   Field '{$field}':\n";
        foreach ($fieldErrors as $error) {
            echo "     - {$error}\n";
        }
    }
}
echo "\n";

// 7. Multiple validation errors
echo "7. Testing validation: multiple errors...\n";
$invalidUser = new UserItem();
$invalidUser->setName('');  // Empty name
$invalidUser->setEmail('bad-email');  // Invalid email
$invalidUser->setAge(150);  // Age too high
$invalidUser->setUsername('ab');  // Username too short

if (!$invalidUser->isValid()) {
    echo "   ✗ Validation failed with multiple errors:\n";
    $errors = $invalidUser->getValidationErrors();
    foreach ($errors as $field => $fieldErrors) {
        echo "   Field '{$field}':\n";
        foreach ($fieldErrors as $error) {
            echo "     - {$error}\n";
        }
    }
}
echo "\n";

// 8. Attempting to save invalid user (should fail)
echo "8. Attempting to save invalid user...\n";
$invalidUser = new UserItem();
$invalidUser->setName('Test User');
$invalidUser->setEmail('invalid-email');
$invalidUser->setAge(25);
$invalidUser->setUsername('testuser');

if ($invalidUser->isValid()) {
    $repo->saveItem($invalidUser);
    echo "   ✓ User saved (unexpected)\n";
} else {
    echo "   ✗ Cannot save invalid user:\n";
    $errors = $invalidUser->getValidationErrors();
    foreach ($errors as $field => $fieldErrors) {
        echo "   Field '{$field}': " . implode(', ', $fieldErrors) . "\n";
    }
}

echo "\n=== Example Complete ===\n";
