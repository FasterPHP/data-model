# Coding Guidelines

## Standard

This project follows **PSR-12** as enforced by PHP_CodeSniffer (see `phpcs.xml.dist`).

Run the linter:

```bash
vendor/bin/phpcs
```

## PHP Version

PHP 8.2+ with `declare(strict_types=1)` in every file.

## Namespace & Autoloading

- PSR-4: `FasterPhp\DataModel\` maps to `src/`
- Tests share the same namespace and map to `tests/`

## Naming Conventions

### Classes

| Layer | Pattern | Example |
|---|---|---|
| Item | `{Prefix}Item` | `UserItem`, `TicketItem` |
| Set | `{Prefix}Set` | `UserSet`, `TicketSet` |
| Repository | `{Prefix}Repository` | `UserRepository`, `TicketRepository` |

The `Util` class infers related class names from this convention. Only override `$itemClassName` / `$setClassName` if you break it.

### Fields & Methods

- Field names: **camelCase** matching database column names (`userId`, `createdAt`)
- Getters/setters: `get{Field}()` / `set{Field}()` — these work via `__call` magic on `Item`
- Constants: **UPPER_SNAKE_CASE** (`FIELDS`, `FIELDS_READONLY`, `DB_NAME`)

### Database

- `DB_NAME` and `TABLE_NAME` are defined as protected constants on the Repository
- `ID_FIELD` is the database column name for the primary key (e.g. `userId`)
- `ID_INTERNAL` is the internal alias (defaults to `id`)

## Class Structure

### Item classes

1. Trait `use` statements
2. `ID_FIELD` constant
3. `FIELDS` constant (writable fields)
4. `FIELDS_READONLY`, `FIELDS_EXTERNAL`, `FIELDS_AGGREGATE` constants (as needed)
5. `DEFAULTS` constant (optional)
6. `VALIDATORS` constant (optional, only with validation traits)
7. Getter and setter methods

### Repository classes

Override SQL clause methods piecemeal for joins and custom queries:

- `getSelectClause()` — field list
- `getFromClause()` — table and joins
- `getGroupByClause()` — aggregation

## Dependencies

- No hidden global state — PDO is always injected explicitly
- Validation is opt-in via traits (`ValidatableTrait`, `LaminasValidatorTrait`)
- Items without validation traits have zero validation overhead

## Doc Comments

- Use PHPDoc blocks for class-level documentation
- Method-level PHPDoc is optional when types are fully expressed by signatures
- Use `@template` annotations on generic classes (see `Repository`)
