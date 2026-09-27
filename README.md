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
composer require faster-php/data-model
```

## Quick Start

### 1. Define Your Item Class

```php
use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;

class UserItem extends Item
{
    public const ID_FIELD = 'userId';

    public const FIELDS = [
        'name' => Field\Varchar::class,
        'email' => Field\Varchar::class,
        'age' => Field\Integer::class,
        'created' => Field\Datetime::class,
    ];
}
```

**Note:** The `id` field is managed automatically — do not declare it in `FIELDS`. The base `Item` class creates it implicitly using `ID_FIELD` (database column name) and `ID_TYPE` (default `Field\Integer::class`). Override `ID_TYPE` if your id uses a different field type.

**Note:** Getters and setters are provided automatically via `__call` magic methods. `$user->getName()` and `$user->setName('value')` work for any field defined in `FIELDS`. You can optionally define explicit methods for IDE autocompletion and static analysis. The `setId()` magic setter is blocked — use `getId()` to read, and `assignId()` for internal/Repository use only.

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
    protected const DB_NAME = 'myapp';
    protected const TABLE_NAME = 'users';
}
```

**Note:** By default, `Set` and `Repository` automatically infer related class names using the `ClassNameUtil` class if you follow the `{Prefix}Item/{Prefix}Set/{Prefix}Repository` naming convention. You only need to override `$itemClassName` or `$setClassName` if your naming doesn't follow this convention.

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
$repo->saveItem($user);
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
$user->getActive(); // Returns true or false (bool)

// Database returns '2024-01-15 10:30:00' (string)
$user->getCreated(); // Returns DateTime object
```

### Extending for Complex Queries

Avoid N+1 problems by extending your classes to add joins. These clause hooks remain the simplest
way to adjust one part of a query; see [Replacing the Whole Query](#replacing-the-whole-query) when
they cannot express what you need, such as a join that binds a parameter:

```php
class TicketItem extends Item
{
    public const ID_FIELD = 'ticketId';

    public const FIELDS = [
        'title' => Field\Varchar::class,
        'assignedTo' => Field\Integer::class,
    ];

    public const FIELDS_EXTERNAL = [
        'assigneeName' => Field\Varchar::class, // From join
    ];
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

### Replacing the Whole Query

The clause hooks above are the fine-grained extension point. `buildSelectQuery()` is the coarse
one: it returns the repository's base query as a `SqlQuery`, and every Set and Item retrieval starts
from it. Its default implementation composes `getSelectClause()`, `getFromClause()` and
`getGroupByClause()`, so overriding a clause hook alone continues to work exactly as before.

The hook receives no filters. The repository applies the caller's filters to whatever query it
returns, and the query still receives the repository's sorting, pagination and Item construction, so
it is a supported alternative to escaping to `PDO::prepare()` directly.

The string-returning clause hooks cannot bind parameters. Suppose each ticket should carry a count of
its public comments. That needs a derived table with a bound visibility, which the coarse hook can
express by deriving from the parent query and replacing its select list and from source:

```php
use FasterPhp\DataModel\Sql\SqlFragment;
use FasterPhp\DataModel\Sql\SqlQuery;

class TicketItem extends Item
{
    public const ID_FIELD = 'ticketId';

    public const FIELDS = [
        'title' => Field\Varchar::class,
        'assignedTo' => Field\Integer::class,
    ];

    public const FIELDS_EXTERNAL = [
        'commentCount' => Field\Integer::class, // From the derived table
    ];
}

class TicketRepository extends Repository
{
    protected const DB_NAME = 'myapp';
    protected const TABLE_NAME = 'tickets';

    protected function buildSelectQuery(): SqlQuery
    {
        $query = parent::buildSelectQuery();

        return $query->with(
            select: new SqlFragment(
                $query->getSelect()->getSql() . ', COALESCE(c.commentCount, 0) AS commentCount'
            ),
            from: new SqlFragment(
                $query->getFrom()->getSql()
                . ' LEFT JOIN (SELECT ticketId, COUNT(*) AS commentCount FROM comments'
                . ' WHERE visibility = :q_visibility GROUP BY ticketId) c'
                . ' ON c.ticketId = tickets.ticketId',
                [':q_visibility' => 'public'],
            ),
        );
    }
}

$tickets = $repo->getSetWithParams(['assignedTo' => 2]);
```

The retrieval above executes the following SQL (line breaks added), binding `:q_visibility` to
`'public'` and `:assignedTo` to `'2'`. The select list and from source come from the hook; the WHERE
clause is the caller's filter, added by the repository:

```sql
SELECT `tickets`.`ticketId` AS `id`, `tickets`.`title`, `tickets`.`assignedTo`,
       COALESCE(c.commentCount, 0) AS commentCount
FROM `tickets`
    LEFT JOIN (SELECT ticketId, COUNT(*) AS commentCount FROM comments
               WHERE visibility = :q_visibility GROUP BY ticketId) c
    ON c.ticketId = tickets.ticketId
WHERE `tickets`.`assignedTo` = :assignedTo
```

`getItemWithId(3)` executes the same query with a WHERE condition on `` `tickets`.`ticketId` ``,
so it returns ticket 3 or nothing.

A query returned from the hook must follow these rules:

- **It must not depend on the call.** The hook takes no arguments, and the same query is the base of
  every retrieval. Do not vary it through repository state set before a call.
- **Per-call conditions are filters.** Pass them to `getSetWithParams()`, `getItemWithParams()` or
  `getItemWithId()`. The repository ANDs filters on ordinary fields onto the query's WHERE clause and
  filters on aggregate fields onto its HAVING clause, grouping each side in parentheses if the query
  already has a condition there, so an `OR` in either keeps its meaning. To add a condition to every
  retrieval, override `getWhereSqlAndParams()` or `getHavingSqlAndParams()`.
- **The base table keeps its name.** Filters on declared fields and on the ID column, and
  `getItemWithId()`, qualify columns with the table name, as in `` `tickets`.`ticketId` ``. Aliasing the base table
  (`tickets t`) makes those references fail with an unknown-column error.
- **Placeholders should not look like field names.** A filter on `status` binds `:status`. If the
  query binds `:status` to a different value, retrieval throws before anything is executed. A prefix
  such as `:q_visibility` rules the clash out.
- **A query for aggregate fields supplies its own GROUP BY.** The default query groups by the id when
  the Item declares `FIELDS_AGGREGATE`, and a query derived from it keeps that grouping; a query
  built from scratch must add one.

A `SqlQuery` holds the clauses of a SELECT: `select` and `from` are required, `where`, `groupBy`
and `having` are optional. Each is a `SqlFragment`, which carries a piece of SQL together with the
parameters that SQL binds, so the two can never become separated. A query can also be built from
scratch with named arguments:

```php
$query = new SqlQuery(
    select: new SqlFragment('tickets.ticketId AS `id`, tickets.title'),
    from: new SqlFragment('tickets'),
    where: new SqlFragment('tickets.archived = :q_archived', [':q_archived' => 0]),
);
```

Queries are immutable. `with()` derives a new query rather than modifying the original, carrying
over every clause not replaced, and `SqlQuery::NONE` removes an optional one. `andWhere()` and
`andHaving()` derive a query with a condition ANDed onto the existing clause, as the repository does
with filters:

```php
$query = $parentQuery
    ->with(having: SqlQuery::NONE)
    ->andWhere(new SqlFragment('tickets.priority >= :q_priority', [':q_priority' => 3]));
```

If two clauses bind the same parameter name to different values, rendering the query throws rather
than silently discarding one of the bindings, and so does ANDing a condition that rebinds a name the
clause already binds.

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

Pagination is opt-in. A repository constructed without a paginator, or with only a `Sort`, is
unlimited: its queries carry no `LIMIT` and a Set holds every matching row. Application-wide
defaults set with `Paginator\Base::setDefaultMaxItemsPerPage()` apply only to paginators you
construct yourself, so they never truncate a query you did not ask to be paged. To page a
repository built without a paginator, call `setMaxItemsPerPage()` on it after construction.

Single-item lookups such as `getItemWithId()` and `getItemWithParams()` always fetch one row,
whatever the repository's paginator says.

To pick one row out of several matches, such as the latest, pass a sort to `getItemWithParams()`:

```php
// The most recent user with this email address
$user = $repo->getItemWithParams(
    ['email' => 'john@example.com'],
    sort: new Sort('id', Sort::DESCENDING),
);
```

That sort orders this lookup only, in place of the repository's sort, and leaves the repository's
sort as it was. Do not call `setSort()` for a one-off lookup like this: it changes the repository's
own sort, so every later retrieval through the same repository, including `getSetWithParams()` and
`getSetOfAll()`, would be ordered by it too. Sorting by `id` works on joined repositories as well,
because ORDER BY may use the `id` select alias, which also reads the same whatever the ID column is
called.

### Validation (Optional)

Validation is opt-in via traits. Add validation to your Item classes:

#### Using Laminas Validators

```php
use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\Validation\ValidatableTrait;
use FasterPhp\DataModel\Validation\LaminasValidatorTrait;
use Laminas\Validator;

class ValidatedUserItem extends Item
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
            new Validator\StringLength(['min' => 2, 'max' => 100]),
            message: 'Name must be between 2 and 100 characters',
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

// Usage
$user = new ValidatedUserItem();
$user->setName('J'); // Too short
$user->setEmail('invalid-email');

if (!$user->isValid()) {
    $errors = $user->getValidationErrors();
    // [
    //   'name' => ['Name must be between 2 and 100 characters'],
    //   'email' => ['...is not a valid email address...']
    // ]
}
```

#### Using Other Frameworks (Symfony, Laravel, etc.)

`ValidatableTrait` does not require Laminas. Each `validate{FieldName}()` method just needs to return any object with `isValid($value): bool` and `getMessages(): array`. You write a small adapter class once per project to bridge your framework's validator, then use it in all your Items. See the examples directory for complete working integrations:

- `examples/06-symfony-validation.php` - Symfony Validator adapter and usage
- `examples/07-laravel-validation.php` - Laravel Validator adapter and usage
- `examples/08-custom-validators.php` - Generic custom validator (any framework)

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

// NOT NULL checks
$users = $repo->getSetWithParams(
    ['deletedAt' => null],
    ['deletedAt' => Repository::NOT_EQUALS]  // WHERE deletedAt IS NOT NULL
);

// An empty array matches no rows
$users = $repo->getSetWithParams([
    'id' => []  // WHERE 1 = 0
]);
```

Filter keys are used as SQL identifiers, so each must be a plain or dot-qualified name
(letters, digits and underscores). Anything else, such as an expression, is rejected with an
exception. The same rule applies to sort fields.

Filters on columns of the base table are qualified with the table name, so they stay unambiguous
when a repository joins other tables: declared fields (`FIELDS` and `FIELDS_READONLY`), and the ID
column, by its own name or as `id`. On a repository for `users` whose `ID_FIELD` is `userId`, both
`'userId' => 7` and `'id' => 7` render `` `users`.`userId` ``, so the `id` examples above filter on
the real column rather than on the `id` alias of the select list. External and aggregate fields,
dot-qualified keys and any other key are used as given. Placeholders are always named after the key
as given: `'id' => 7` binds `:id`.

### Batch Operations

```php
// Save multiple items efficiently
$set = $repo->getSetOfAll();
foreach ($set as $user) {
    $user->setActive(true);
}
$repo->saveSet($set, true); // In one transaction: every write or none

// Delete items
$user = $repo->getItemWithId(123);
$user->setToDelete();
$repo->saveItem($user);
```

### Transactions

Passing `true` as the second argument to `saveItem()` or `saveSet()` wraps the save in a transaction,
but the repository only begins one if none is already active on the connection, and only commits or
rolls back a transaction it began:

- **No transaction active**: the repository begins one, commits it when every statement succeeds and
  rolls it back on failure, rethrowing the exception. Items are marked persisted only after the
  commit. After a rollback every Item keeps its pre-save state (still new or still modified, with no
  generated id), so saving the same Item or Set again repeats every write.
- **Transaction already active**: the save joins it. Nothing is begun, committed or rolled back, and a
  failure propagates for the transaction's owner to handle. Items are marked persisted as soon as each
  statement succeeds, so a new Item's generated id is available straight away.

To make writes through several repositories atomic, own the transaction yourself on the shared
connection:

```php
$orderRepo = new OrderRepository($pdo);
$lineRepo  = new OrderLineRepository($pdo);

$pdo->beginTransaction();
try {
    $orderRepo->saveItem($order, true);

    // The order's generated id is available before commit
    foreach ($lines as $line) {
        $line->setOrderId($order->getId());
    }
    $lineRepo->saveSet($lines, true);

    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

Because the repositories cannot see a transaction they did not begin, rolling back your own
transaction leaves the Items saved within it claiming to be persisted. After such a rollback, discard
or reload those Items rather than saving them again.

### SQL Helper Methods

```php
use FasterPhp\DataModel\Sql\SqlUtil;

// Quote identifiers (validated; anything but a plain or dot-qualified name throws)
$sql = SqlUtil::ident('users.userId'); // `users`.`userId`

// Generate placeholders (unique per key; a key needing sanitisation gets a suffix)
$param = SqlUtil::placeholder('userId');  // :userId
$param = SqlUtil::placeholder('user-id'); // :user_id_f01b6fab

// Add LIKE wildcards
$value = SqlUtil::likeWildcards('test', Repository::CONTAINS); // %test%

// Expand IN clause
[$sql, $params] = SqlUtil::expandIn('userId', [1, 2, 3]);
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
- `examples/03-validation.php` - Validation with Laminas validators
- `examples/04-joins.php` - Complex queries with joins and hand-written queries
- `examples/05-batch-operations.php` - Batch updates and deletes
- `examples/06-symfony-validation.php` - Symfony Validator integration
- `examples/07-laravel-validation.php` - Laravel Validator integration
- `examples/08-custom-validators.php` - Generic custom validator (any framework)

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
