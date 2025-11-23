<?php

/**
 * Example 06: Framework Validator Integration
 * 
 * This example demonstrates:
 * - Overriding buildValidatorChain() to use custom validators
 * - Integrating with framework-specific validation (Symfony, Laravel, etc.)
 * - Creating a custom validator chain that mimics framework behavior
 * - Maintaining the same validation API (isValid(), getValidationErrors())
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Set;
use FasterPhp\DataModel\Repository;
use FasterPhp\DataModel\Field;

/**
 * Custom validator chain that mimics framework validator interfaces.
 * In real applications, this would be your framework's validator (e.g., Symfony Validator, Laravel Validator).
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
    public const ID_FIELD = 'userId';
    
    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
    ];
    
    public const VALIDATORS = [
        'name' => [
            ['class' => \Laminas\Validator\StringLength::class, 'options' => ['min' => 3, 'max' => 60]],
        ],
        'email' => [
            ['class' => \Laminas\Validator\EmailAddress::class],
        ],
    ];
}

/**
 * Item using custom validator chain (framework integration pattern).
 */
class CustomUserItem extends Item
{
    public const ID_FIELD = 'userId';
    
    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
        'username' => Field\Varchar::class,
    ];
    
    // Custom validation config (not Laminas-specific)
    public const VALIDATORS = [
        'name' => [
            ['rule' => 'minLength', 'value' => 3, 'message' => 'Name must be at least 3 characters'],
            ['rule' => 'maxLength', 'value' => 60, 'message' => 'Name must not exceed 60 characters'],
        ],
        'email' => [
            ['rule' => 'email', 'message' => 'Email must be a valid email address'],
        ],
        'username' => [
            ['rule' => 'alphanumeric', 'message' => 'Username must contain only letters and numbers'],
            ['rule' => 'minLength', 'value' => 3, 'message' => 'Username must be at least 3 characters'],
        ],
    ];
    
    /**
     * Override buildValidatorChain to use custom validators instead of Laminas.
     * This is where you would integrate Symfony Validator, Laravel Validator, etc.
     */
    protected function buildValidatorChain(string $fieldName, array $configs)
    {
        $chain = new CustomValidatorChain();
        
        foreach ($configs as $config) {
            $rule = $config['rule'];
            $message = $config['message'];
            $value = $config['value'] ?? null;
            
            // Map rules to validation logic
            switch ($rule) {
                case 'minLength':
                    $chain->addValidator(
                        fn($v) => strlen((string)$v) >= $value,
                        $message
                    );
                    break;
                    
                case 'maxLength':
                    $chain->addValidator(
                        fn($v) => strlen((string)$v) <= $value,
                        $message
                    );
                    break;
                    
                case 'email':
                    $chain->addValidator(
                        fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
                        $message
                    );
                    break;
                    
                case 'alphanumeric':
                    $chain->addValidator(
                        fn($v) => ctype_alnum((string)$v),
                        $message
                    );
                    break;
            }
        }
        
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
echo "   To integrate with Symfony Validator:\n";
echo "   - Override buildValidatorChain() to return Symfony\\Component\\Validator\\Validator\\ValidatorInterface\n";
echo "   - Use Symfony constraints in VALIDATORS config\n";
echo "   - Call \$validator->validate(\$value, \$constraints) in validate() method\n";
echo "\n";
echo "   To integrate with Laravel Validator:\n";
echo "   - Override buildValidatorChain() to return Illuminate\\Validation\\Validator\n";
echo "   - Use Laravel validation rules in VALIDATORS config\n";
echo "   - Call Validator::make([\$field => \$value], [\$field => \$rules])\n";
echo "\n";
echo "   The key is that buildValidatorChain() has no return type hint,\n";
echo "   so you can return any validator object that implements isValid() and getMessages().\n";

echo "\n=== Example Complete ===\n";
