# Testing Guidelines

## Framework

PHPUnit 11.5+ configured via `phpunit.xml.dist`.

## Running Tests

```bash
# With coverage report (output to build/)
vendor/bin/phpunit

# Without coverage (faster)
vendor/bin/phpunit --no-coverage
```

Tests stop on first defect (`stopOnDefect="true"`).

## Test Organisation

- Test files live in `tests/` under the `FasterPhp\DataModel` namespace
- Test models (stubs, fixtures) live in `tests/TestModel/`
- One test class per source class, named `{Class}Test` (e.g. `ItemTest`, `SetTest`)
- Specialised test suites for cross-cutting features use descriptive names (e.g. `AggregateFieldsTest`, `ReadonlyFieldsTest`)

## Test Model Conventions

Test models follow the same `{Prefix}Item` / `{Prefix}Set` / `{Prefix}Repository` naming convention as production code:

- `ValidItem` / `ValidSet` / `ValidRepository` — standard test model
- `ReadonlyItem` / `ReadonlySet` / `ReadonlyRepository` — read-only fields
- `ExternalItem` / `ExternalSet` / `ExternalRepository` — external fields
- `AggregateItem` / `AggregateSet` / `AggregateRepository` — aggregate fields

## Patterns

### PDO Mocking

Use `$this->createStub(PDO::class)` for tests that need a PDO instance but don't execute queries. For integration tests that execute SQL, configure the database connection via environment variables in `phpunit.xml.dist`.

### Testing Exceptions

Use `$this->expectException()` and `$this->expectExceptionMessage()` before the call that should throw:

```php
$this->expectException(Exception::class);
$this->expectExceptionMessage('Database name not set');
$repo->getDbName();
```

### Assertions

- Use strict assertions (`assertSame` over `assertEquals`) wherever possible
- Test both positive and negative cases (e.g. `testIsDirtyTrue` / `testIsDirtyFalse`)

## Database Tests

Tests requiring a real database connection (e.g. `RepositoryDbTest`) use environment variables:

- `DB_DSN` — PDO connection string
- `DB_USER` — database username
- `DB_PASS` — database password

These are pre-configured in `phpunit.xml.dist` and should be overridden locally as needed.
