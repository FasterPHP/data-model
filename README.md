# FasterPHP/DataModel

A lightweight, high-performance data model library for PHP 8.2+ that provides strict typing, lazy loading, and efficient database operations without the complexity of full ORMs like Doctrine.

## Features

- **Strict Type Casting**: Compensates for MySQL's string-only return types with automatic type conversion
- **Lazy Loading**: Memory-efficient design - Sets are 2D arrays, Items are 1D arrays, Fields instantiated only on access
- **Easy Extension**: Simple patterns for joins, read-only fields, and complex queries to avoid N+1 problems
- **Built-in Pagination & Sorting**: Efficient COUNT wrapping that leverages MySQL's query optimizer and cache
- **No SQL Abstractions**: Write raw SQL for maximum flexibility and performance
- **Dependency Injection**: Explicit PDO injection with no hidden global state
- **Optional Validation**: Opt-in validation via traits - use Laminas, Symfony, Laravel, or custom validators
- **Modern PHP**: PHP 8.2+ with typed properties, match expressions, and JsonSerializable support

## Requirements

- PHP 8.2 or higher
- PDO extension
- laminas/laminas-validator (optional, only if using validation features)

## Installation

```bash
composer require fasterphp/data-model
```

## Quick Start

### 1. Define Your Item Class

```php
use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;

class UserItem extends Item
{
    public const DB_NAME = 'myapp';
    public const TABLE_NAME = 'users';

    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
        'age' => Field\Integer::class,
        'created' => Field\Datetime::class,
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

    // ... other getters and setters
}
```

### 2. Define Your Set Class

```php
use FasterPhp\DataModel\Set;

class UserSet extends Set
{
    // No properties needed - class names inferred from naming convention
}
```

### 3. Define Your Repository

```php
use FasterPhp\DataModel\Repository;

class UserRepository extends Repository
{
    // No properties needed - class names inferred from naming convention
}
```

**Note:** By default, `Set` and `Repository` automatically infer related class names using the `Util` class if you follow the `{Prefix}Item/{Prefix}Set/{Prefix}Repository` naming convention. You only need to override `$itemClassName` or `$setClassName` if your naming doesn't follow this convention.

### 4. Use Your Repository

```php
// Inject PDO via dependency injection
$pdo = new PDO('mysql:host=localhost;dbname=myapp', 'user', 'pass');
$repo = new UserRepository($pdo);

// Fetch a single user
$user = $repo->getItemWithId(123);
echo $user->getName();

// Fetch multiple users with comparison operators
$users = $repo->getSetWithParams(
    ['age' => 18],
    ['age' => Repository::GREATER]
);
foreach ($users as $user) {
    echo $user->getName() . "\n";
}

// Create and save a new user
$user = new UserItem();
$user->setName('John Doe');
$user->setEmail('john@example.com');

if ($user->isValid()) {
    $repo->saveItem($user);
}
```

## Core Concepts

### Lazy Loading

The library uses extensive lazy loading for memory efficiency:

```php
// Set is just a 2D array until items are accessed
$users = $repo->getSetOfAll(); // Minimal memory usage

// Item is instantiated only when accessed
$firstUser = $users[0]; // Now an Item instance

// Field is instantiated only when accessed
$name = $firstUser->getName(); // Field instance created here
```

### Strict Typing

MySQL returns all values as strings by default. This library automatically casts values to their proper PHP types:

```php
// Database returns '123' (string)
$user->getAge(); // Returns 123 (int)

// Database returns '1' or '0' (string)
$user->isActive(); // Returns true or false (bool)

// Database returns '2024-01-15 10:30:00' (string)
$user->getCreated(); // Returns DateTime object
```

### Extending for Complex Queries

Avoid N+1 problems by extending your classes to add joins:

```php
class TicketItem extends Item
{
    public const FIELDS = [
        'id' => Field\Integer::class,
        'title' => Field\Varchar::class,
        'assignedTo' => Field\Integer::class,
    ];

    public const FIELDS_READONLY = [
        'assigneeName' => Field\Varchar::class, // From join
    ];

    public function getAssigneeName(): ?string
    {
        return $this->getField('assigneeName')->getValue();
    }
}

class TicketRepository extends Repository
{
    protected function getSelectClause(): string
    {
        return parent::getSelectClause() . ', users.name AS assigneeName';
    }

    protected function getFromClause(): string
    {
        return parent::getFromClause()
            . ' LEFT JOIN users ON tickets.assignedTo = users.id';
    }
}

// Now fetch tickets with assignee names in a single query
$tickets = $repo->getSetOfAll();
foreach ($tickets as $ticket) {
    echo $ticket->getTitle() . ' - ' . $ticket->getAssigneeName();
}
```

### Pagination and Sorting

Built-in pagination wraps your query with COUNT for efficiency:

```php
use FasterPhp\DataModel\Sort;
use FasterPhp\DataModel\Paginator\SqlPaginator;

// Create a sort
$sort = new Sort('name', Sort::ASCENDING);

// Create a paginator
$paginator = new SqlPaginator($pdo, $sort);
$paginator->setMaxItemsPerPage(20);
$paginator->setPageNum(1);

// Use with repository
$repo = new UserRepository($pdo, $paginator);
$users = $repo->getSetOfAll();

// Access pagination info
echo "Total users: " . $paginator->getNumItemsTotal();
echo "Page 1 of " . $paginator->getNumPages();
```

### Validation (Optional)

Validation is opt-in via traits. Add validation to your Item classes:

#### Using Laminas Validators

```php
use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Validation\ValidatableTrait;
use FasterPhp\DataModel\Validation\LaminasValidatorTrait;

class ValidatedUserItem extends Item
{
    use ValidatableTrait;
    use LaminasValidatorTrait;

    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
    ];

    public const VALIDATORS = [
        'name' => [
            ['class' => \Laminas\Validator\StringLength::class, 'options' => ['min' => 2, 'max' => 100]],
        ],
        'email' => [
            ['class' => \Laminas\Validator\EmailAddress::class],
        ],
    ];

    // ... getters and setters
}

// Usage
$user = new ValidatedUserItem();
$user->setName('J'); // Too short
$user->setEmail('invalid-email');

if (!$user->isValid()) {
    $errors = $user->getValidationErrors();
    // [
    //   'name' => ['The input is less than 2 characters long'],
    //   'email' => ['The input is not a valid email address']
    // ]
}
```

#### Using Custom Validators (Symfony, Laravel, etc.)

```php
use FasterPhp\DataModel\Validation\ValidatableTrait;

class SymfonyUserItem extends Item
{
    use ValidatableTrait;

    public const VALIDATORS = [
        'email' => [/* Symfony constraint config */],
    ];

    // Implement buildValidatorChain to return object with isValid() and getMessages()
    protected function buildValidatorChain(string $fieldName, array $configs)
    {
        return new SymfonyValidatorAdapter(
            $this->getSymfonyValidator(),
            $this->buildSymfonyConstraints($fieldName, $configs)
        );
    }
}
```

**Note:** Items without validation traits have no validation overhead.

## Advanced Usage

### Search Operations

```php
// Exact match
$users = $repo->getSetWithParams(['name' => 'John']);

// Comparison operators (use second $types parameter)
$users = $repo->getSetWithParams(
    [
        'age' => 18,
        'created' => '2024-01-01',
    ],
    [
        'age' => Repository::GREATER,
        'created' => Repository::LESS,
    ]
);

// LIKE searches (use second $types parameter)
$users = $repo->getSetWithParams(
    ['name' => 'John'],
    ['name' => Repository::STARTS]  // name LIKE 'John%'
);

// IN clause
$users = $repo->getSetWithParams([
    'id' => [1, 2, 3, 4, 5]
]);

// NULL checks
$users = $repo->getSetWithParams([
    'deletedAt' => null  // WHERE deletedAt IS NULL
]);
```

### Batch Operations

```php
// Save multiple items efficiently
$set = $repo->getSetOfAll();
foreach ($set as $user) {
    $user->setActive(true);
}
$repo->saveSet($set); // Single transaction

// Delete items
$user = $repo->getItemWithId(123);
$user->setToDelete();
$repo->saveItem($user);
```

### SQL Helper Methods

```php
use FasterPhp\DataModel\Sql;

// Quote identifiers
$sql = Sql::ident('users.userId'); // `users`.`userId`

// Generate placeholders
$param = Sql::placeholder('user-id'); // :user_id

// Add LIKE wildcards
$value = Sql::likeWildcards('test', Repository::CONTAINS); // %test%

// Expand IN clause
[$sql, $params] = Sql::expandIn('userId', [1, 2, 3]);
// Returns: ['userId IN (:p0,:p1,:p2)', [':p0' => 1, ':p1' => 2, ':p2' => 3]]
```

## Field Types

Available field types with automatic type casting:

- `Field\Boolean` - Casts to `bool`
- `Field\Integer` - Casts to `int`
- `Field\Decimal` - Casts to `string` (for precision)
- `Field\Double` - Casts to `float`
- `Field\Varchar` - Casts to `string`
- `Field\Char` - Casts to `string`
- `Field\Text` - Casts to `string`
- `Field\Datetime` - Casts to `DateTime` object
- `Field\Json` - Casts to `array` (auto encode/decode)
- `Field\Enum` - Casts to `string` with allowed values

## Working with FasterPHP/db

This library works seamlessly with the FasterPHP/db package for additional features:

```php
use FasterPhp\Db\ConnectionManager;

// Get a connection with auto-reconnect and statement caching
$db = ConnectionManager::getConnection('default');

// Pass to repository (Db extends PDO)
$repo = new UserRepository($db);
```

The `Db` class provides:
- Automatic reconnection on timeout
- Prepared statement caching
- Connection pooling via ConnectionManager

## Examples

See the `examples/` directory for complete working examples:

- `examples/01-basic-usage.php` - Basic CRUD operations
- `examples/02-pagination.php` - Pagination and sorting
- `examples/03-validation.php` - Validation and error handling
- `examples/04-joins.php` - Complex queries with joins
- `examples/05-batch-operations.php` - Batch updates and deletes
- `examples/06-custom-validators.php` - Framework validator integration

## Testing

```bash
# Run tests
./vendor/bin/phpunit

# Run with coverage (requires xdebug or pcov)
./vendor/bin/phpunit --coverage-html coverage/
```

## License

MIT License. See LICENSE file for details.

## Contributing

Contributions are welcome! Please submit pull requests or open issues on GitHub.

## Credits

Developed by FasterPHP for rapid prototyping and production use.
