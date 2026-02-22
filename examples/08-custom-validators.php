<?php

/**
 * Example 06: Framework Validator Integration
 *
 * This example demonstrates:
 * - Using validate{FieldName}() methods with custom (non-Laminas) validator chains
 * - Integrating with framework-specific validation (Symfony, Laravel, etc.)
 * - Creating a custom validator chain that satisfies the duck-type contract
 * - Maintaining the same validation API (isValid(), getValidationErrors())
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Set;
use FasterPhp\DataModel\Repository;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Validation\ValidatableTrait;
use FasterPhp\DataModel\Validation\LaminasValidatorTrait;
use Laminas\Validator;

/**
 * Custom validator chain that mimics framework validator interfaces.
 * In real applications, this would be your framework's validator (e.g., Symfony Validator, Laravel Validator).
 *
 * ValidatableTrait requires each validate{FieldName}() method to return an object with:
 *   - isValid($value): bool
 *   - getMessages(): array
 */
class CustomValidatorChain
{
    private array $validators = [];
    private array $messages = [];

    public function addValidator(callable $validator, string $message): void
    {
        $this->validators[] = ['validator' => $validator, 'message' => $message];
    }

    public function isValid($value): bool
    {
        $this->messages = [];

        foreach ($this->validators as $validatorConfig) {
            $validator = $validatorConfig['validator'];
            if (!$validator($value)) {
                $this->messages[] = $validatorConfig['message'];
            }
        }

        return empty($this->messages);
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}

/**
 * Item using default Laminas validators (for comparison).
 */
class StandardUserItem extends Item
{
    use ValidatableTrait;
    use LaminasValidatorTrait;

    public const ID_FIELD = 'userId';

    public const FIELDS = [
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
    ];

    protected function validateName(): Validator\ValidatorChain
    {
        $chain = $this->createChain();
        $this->attachValidator(
            $chain,
            new Validator\StringLength(['min' => 3, 'max' => 60]),
            message: 'Name must be between 3 and 60 characters',
        );
        return $chain;
    }

    protected function validateEmail(): Validator\ValidatorChain
    {
        $chain = $this->createChain();
        $this->attachValidator($chain, new Validator\EmailAddress());
        return $chain;
    }
}

/**
 * Item using custom validator chain (framework integration pattern).
 *
 * Uses only ValidatableTrait (no LaminasValidatorTrait). Each validate{FieldName}()
 * method builds and returns a CustomValidatorChain instead of a Laminas ValidatorChain.
 */
class CustomUserItem extends Item
{
    use ValidatableTrait;

    public const ID_FIELD = 'userId';

    public const FIELDS = [
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
        'username' => Field\Varchar::class,
    ];

    protected function validateName(): CustomValidatorChain
    {
        $chain = new CustomValidatorChain();
        $chain->addValidator(
            fn($v) => strlen((string)$v) >= 3,
            'Name must be at least 3 characters'
        );
        $chain->addValidator(
            fn($v) => strlen((string)$v) <= 60,
            'Name must not exceed 60 characters'
        );
        return $chain;
    }

    protected function validateEmail(): CustomValidatorChain
    {
        $chain = new CustomValidatorChain();
        $chain->addValidator(
            fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
            'Email must be a valid email address'
        );
        return $chain;
    }

    protected function validateUsername(): CustomValidatorChain
    {
        $chain = new CustomValidatorChain();
        $chain->addValidator(
            fn($v) => ctype_alnum((string)$v),
            'Username must contain only letters and numbers'
        );
        $chain->addValidator(
            fn($v) => strlen((string)$v) >= 3,
            'Username must be at least 3 characters'
        );
        return $chain;
    }
}

class CustomUserSet extends Set
{
}

class CustomUserRepository extends Repository
{
    protected const DB_NAME = 'example';
    protected const TABLE_NAME = 'users';
}

// Example usage
echo "=== FasterPHP Data Model - Custom Validators Example ===\n\n";

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
        username VARCHAR(60)
    )
");

echo "   ✓ Database setup complete\n\n";

$repo = new CustomUserRepository($pdo);

// 2. Standard Laminas validation (for comparison)
echo "2. Standard Laminas validation...\n";
$standardUser = new StandardUserItem();
$standardUser->setName('ab');  // Too short
$standardUser->setEmail('invalid-email');

if (!$standardUser->isValid()) {
    echo "   ✗ Validation failed (Laminas):\n";
    foreach ($standardUser->getValidationErrors() as $field => $errors) {
        echo "   Field '{$field}':\n";
        foreach ($errors as $error) {
            echo "     - {$error}\n";
        }
    }
}
echo "\n";

// 3. Custom validation - valid user
echo "3. Custom validation - valid user...\n";
$validUser = new CustomUserItem();
$validUser->setName('Alice Smith');
$validUser->setEmail('alice@example.com');
$validUser->setUsername('alice123');

if ($validUser->isValid()) {
    $repo->saveItem($validUser);
    echo "   ✓ User created successfully with ID: {$validUser->getId()}\n";
    echo "   Name: {$validUser->getName()}\n";
    echo "   Email: {$validUser->getEmail()}\n";
    echo "   Username: {$validUser->getUsername()}\n";
}
echo "\n";

// 4. Custom validation - name too short
echo "4. Custom validation - name too short...\n";
$invalidUser = new CustomUserItem();
$invalidUser->setName('ab');
$invalidUser->setEmail('test@example.com');
$invalidUser->setUsername('testuser');

if (!$invalidUser->isValid()) {
    echo "   ✗ Validation failed (custom):\n";
    foreach ($invalidUser->getValidationErrors() as $field => $errors) {
        echo "   Field '{$field}':\n";
        foreach ($errors as $error) {
            echo "     - {$error}\n";
        }
    }
}
echo "\n";

// 5. Custom validation - invalid email
echo "5. Custom validation - invalid email...\n";
$invalidUser = new CustomUserItem();
$invalidUser->setName('Bob Jones');
$invalidUser->setEmail('not-an-email');
$invalidUser->setUsername('bobjones');

if (!$invalidUser->isValid()) {
    echo "   ✗ Validation failed (custom):\n";
    foreach ($invalidUser->getValidationErrors() as $field => $errors) {
        echo "   Field '{$field}':\n";
        foreach ($errors as $error) {
            echo "     - {$error}\n";
        }
    }
}
echo "\n";

// 6. Custom validation - non-alphanumeric username
echo "6. Custom validation - non-alphanumeric username...\n";
$invalidUser = new CustomUserItem();
$invalidUser->setName('Charlie Brown');
$invalidUser->setEmail('charlie@example.com');
$invalidUser->setUsername('charlie@brown!');

if (!$invalidUser->isValid()) {
    echo "   ✗ Validation failed (custom):\n";
    foreach ($invalidUser->getValidationErrors() as $field => $errors) {
        echo "   Field '{$field}':\n";
        foreach ($errors as $error) {
            echo "     - {$error}\n";
        }
    }
}
echo "\n";

// 7. Custom validation - multiple errors
echo "7. Custom validation - multiple errors...\n";
$invalidUser = new CustomUserItem();
$invalidUser->setName('ab');  // Too short
$invalidUser->setEmail('bad-email');  // Invalid email
$invalidUser->setUsername('ab');  // Too short

if (!$invalidUser->isValid()) {
    echo "   ✗ Validation failed with multiple errors (custom):\n";
    foreach ($invalidUser->getValidationErrors() as $field => $errors) {
        echo "   Field '{$field}':\n";
        foreach ($errors as $error) {
            echo "     - {$error}\n";
        }
    }
}
echo "\n";

// 8. Framework integration notes
echo "8. Framework integration notes...\n";
echo "   The extension point is validate{FieldName}() methods.\n";
echo "   Each method returns any object with isValid(\$value) and getMessages().\n";
echo "\n";
echo "   To integrate with Symfony Validator:\n";
echo "   - Define validate{FieldName}() methods that return a Symfony adapter\n";
echo "   - The adapter wraps Symfony's validate() call behind isValid()/getMessages()\n";
echo "\n";
echo "   To integrate with Laravel Validator:\n";
echo "   - Define validate{FieldName}() methods that return a Laravel adapter\n";
echo "   - The adapter wraps Validator::make() behind isValid()/getMessages()\n";
echo "\n";
echo "   Use only ValidatableTrait (skip LaminasValidatorTrait) for non-Laminas frameworks.\n";

echo "\n=== Example Complete ===\n";
