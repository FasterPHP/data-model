<?php

/**
 * Example 06: Symfony Validator Integration
 *
 * This example demonstrates:
 * - Creating an adapter to bridge Symfony Validator with ValidatableTrait
 * - Using Symfony constraints in validate{FieldName}() methods
 * - The duck-type contract: isValid($value) and getMessages()
 *
 * Requires: composer require symfony/validator
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Set;
use FasterPhp\DataModel\Repository;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Validation\ValidatableTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

/**
 * Adapter that bridges Symfony Validator to the duck-type contract
 * required by ValidatableTrait: isValid($value) and getMessages().
 *
 * Write this class once per project — all Items can reuse it.
 */
class SymfonyValidatorAdapter
{
    private array $constraints;
    private array $messages = [];

    public function __construct(Constraint ...$constraints)
    {
        $this->constraints = $constraints;
    }

    public function isValid($value): bool
    {
        $violations = Validation::createValidator()->validate($value, $this->constraints);
        $this->messages = [];
        foreach ($violations as $violation) {
            $this->messages[] = (string) $violation->getMessage();
        }
        return $this->messages === [];
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}

/**
 * Item using Symfony Validator via the adapter.
 *
 * Uses only ValidatableTrait (no LaminasValidatorTrait needed).
 * Each validate{FieldName}() method returns a SymfonyValidatorAdapter
 * configured with Symfony constraints.
 */
class UserItem extends Item
{
    use ValidatableTrait;

    public const ID_FIELD = 'userId';

    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
        'age' => Field\Integer::class,
    ];

    protected function validateName(): SymfonyValidatorAdapter
    {
        return new SymfonyValidatorAdapter(
            new Assert\NotBlank(message: 'Name is required'),
            new Assert\Length(min: 2, max: 100, minMessage: 'Name must be at least 2 characters'),
        );
    }

    protected function validateEmail(): SymfonyValidatorAdapter
    {
        return new SymfonyValidatorAdapter(
            new Assert\NotBlank(message: 'Email is required'),
            new Assert\Email(message: 'Email must be a valid email address'),
        );
    }

    protected function validateAge(): SymfonyValidatorAdapter
    {
        return new SymfonyValidatorAdapter(
            new Assert\Range(
                min: 18,
                max: 120,
                notInRangeMessage: 'Age must be between {{ min }} and {{ max }}',
            ),
        );
    }
}

class UserSet extends Set
{
}

class UserRepository extends Repository
{
    protected const DB_NAME = 'example';
    protected const TABLE_NAME = 'users';
}

// Example usage
echo "=== FasterPHP Data Model - Symfony Validator Example ===\n\n";

// 1. Setup database connection
echo "1. Setting up database connection...\n";
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("
    CREATE TABLE users (
        userId INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(100),
        email VARCHAR(100),
        age INTEGER
    )
");

echo "   Database setup complete\n\n";

$repo = new UserRepository($pdo);

// 2. Valid user
echo "2. Creating a valid user...\n";
$user = new UserItem();
$user->setName('Alice Smith');
$user->setEmail('alice@example.com');
$user->setAge(30);

if ($user->isValid()) {
    $repo->saveItem($user);
    echo "   User created with ID: {$user->getId()}\n";
}
echo "\n";

// 3. Invalid name
echo "3. Testing validation: name too short...\n";
$user = new UserItem();
$user->setName('X');
$user->setEmail('test@example.com');
$user->setAge(25);

if (!$user->isValid()) {
    echo "   Validation failed:\n";
    foreach ($user->getValidationErrors() as $field => $errors) {
        echo "   Field '{$field}':\n";
        foreach ($errors as $error) {
            echo "     - {$error}\n";
        }
    }
}
echo "\n";

// 4. Invalid email
echo "4. Testing validation: invalid email...\n";
$user = new UserItem();
$user->setName('Bob Jones');
$user->setEmail('not-an-email');
$user->setAge(25);

if (!$user->isValid()) {
    echo "   Validation failed:\n";
    foreach ($user->getValidationErrors() as $field => $errors) {
        echo "   Field '{$field}':\n";
        foreach ($errors as $error) {
            echo "     - {$error}\n";
        }
    }
}
echo "\n";

// 5. Multiple errors
echo "5. Testing validation: multiple errors...\n";
$user = new UserItem();
$user->setName('');
$user->setEmail('bad');
$user->setAge(15);

if (!$user->isValid()) {
    echo "   Validation failed:\n";
    foreach ($user->getValidationErrors() as $field => $errors) {
        echo "   Field '{$field}':\n";
        foreach ($errors as $error) {
            echo "     - {$error}\n";
        }
    }
}

echo "\n=== Example Complete ===\n";
