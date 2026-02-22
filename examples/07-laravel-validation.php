<?php

/**
 * Example 07: Laravel Validator Integration
 *
 * This example demonstrates:
 * - Creating an adapter to bridge Laravel Validator with ValidatableTrait
 * - Using Laravel validation rules in validate{FieldName}() methods
 * - The duck-type contract: isValid($value) and getMessages()
 *
 * Requires: composer require illuminate/validation illuminate/translation
 *
 * Note: In a Laravel application, you can use the Validator facade or inject
 * \Illuminate\Validation\Factory via dependency injection instead of
 * bootstrapping the factory manually as shown here.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Set;
use FasterPhp\DataModel\Repository;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Validation\ValidatableTrait;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;

/**
 * Bootstrap a standalone Laravel Validator factory.
 *
 * In a Laravel app, skip this — the factory is available via DI or Validator facade.
 */
function createValidatorFactory(): ValidatorFactory
{
    $filesystem = new Filesystem();
    $loader = new FileLoader($filesystem, __DIR__ . '/../lang');
    $translator = new Translator($loader, 'en');
    return new ValidatorFactory($translator);
}

/**
 * Adapter that bridges Laravel Validator to the duck-type contract
 * required by ValidatableTrait: isValid($value) and getMessages().
 *
 * Write this class once per project — all Items can reuse it.
 */
class LaravelValidatorAdapter
{
    private string $fieldName;
    private string|array $rules;
    private array $customMessages;
    private ValidatorFactory $factory;
    private array $messages = [];

    public function __construct(
        ValidatorFactory $factory,
        string $fieldName,
        string|array $rules,
        array $customMessages = [],
    ) {
        $this->factory = $factory;
        $this->fieldName = $fieldName;
        $this->rules = $rules;
        $this->customMessages = $customMessages;
    }

    public function isValid($value): bool
    {
        $validator = $this->factory->make(
            [$this->fieldName => $value],
            [$this->fieldName => $this->rules],
            $this->customMessages,
        );

        $this->messages = [];
        if ($validator->fails()) {
            $this->messages = $validator->errors()->get($this->fieldName);
            return false;
        }
        return true;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}

/**
 * Item using Laravel Validator via the adapter.
 *
 * Uses only ValidatableTrait (no LaminasValidatorTrait needed).
 * Each validate{FieldName}() method returns a LaravelValidatorAdapter
 * configured with Laravel validation rules.
 */
class UserItem extends Item
{
    use ValidatableTrait;

    public const ID_FIELD = 'userId';

    public const FIELDS = [
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
        'age' => Field\Integer::class,
    ];

    private ValidatorFactory $validatorFactory;

    public function setValidatorFactory(ValidatorFactory $factory): void
    {
        $this->validatorFactory = $factory;
    }

    protected function validateName(): LaravelValidatorAdapter
    {
        return new LaravelValidatorAdapter(
            $this->validatorFactory,
            'name',
            'required|string|min:2|max:100',
        );
    }

    protected function validateEmail(): LaravelValidatorAdapter
    {
        return new LaravelValidatorAdapter(
            $this->validatorFactory,
            'email',
            'required|email',
        );
    }

    protected function validateAge(): LaravelValidatorAdapter
    {
        return new LaravelValidatorAdapter(
            $this->validatorFactory,
            'age',
            'required|integer|min:18|max:120',
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
echo "=== FasterPHP Data Model - Laravel Validator Example ===\n\n";

// 1. Setup
echo "1. Setting up database and validator factory...\n";
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

// In a Laravel app, you'd inject ValidatorFactory via DI instead
$validatorFactory = createValidatorFactory();

echo "   Database and validator setup complete\n\n";

$repo = new UserRepository($pdo);

// 2. Valid user
echo "2. Creating a valid user...\n";
$user = new UserItem();
$user->setValidatorFactory($validatorFactory);
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
$user->setValidatorFactory($validatorFactory);
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
$user->setValidatorFactory($validatorFactory);
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
$user->setValidatorFactory($validatorFactory);
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
