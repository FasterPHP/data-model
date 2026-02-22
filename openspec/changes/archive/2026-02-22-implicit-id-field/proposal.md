## Why

The `id` field must currently be declared in the `FIELDS` constant of every Item subclass, making it appear user-writable. In practice it maps to an auto-incrementing primary key and is managed internally by the Repository — calling `setId()` before save produces a broken INSERT (column name mismatch between `ID_INTERNAL` and `ID_FIELD`). This confuses both developers and AI agents working with the library, as nothing in the API signals that the field is auto-managed.

## What Changes

- **BREAKING**: Item subclasses no longer declare `'id'` in `FIELDS`. The id field is handled implicitly by the base `Item` class using the existing `ID_FIELD` and `ID_INTERNAL` constants.
- New `ID_TYPE` constant on Item (default `Field\Integer::class`) allows subclasses to specify the id field type without putting it in `FIELDS`.
- `setId()` via `__call` throws a clear exception: the id field is not user-writable.
- New internal `assignId()` method on Item for Repository to write the auto-increment value after INSERT.
- `getSqlValues()` and `getValues()` exclude the id field (it is not part of `FIELDS`).
- `getId()` continues to work as before.
- `isTemp()` and `isDirty()` continue to work as before.
- Composite primary keys are explicitly not supported (by design).
- User-settable primary keys (e.g. UUIDs) are not supported as a first-class feature; the recommended pattern is auto-increment PK + separate unique field.

## Capabilities

### New Capabilities

- `implicit-id-field`: The id field is automatically managed by the Item base class. Covers: automatic id field creation from `ID_FIELD`/`ID_INTERNAL`/`ID_TYPE`, read-only enforcement via `setId()` rejection, internal `assignId()` for Repository use, and exclusion of the id field from user-facing field lists.

### Modified Capabilities

_(none — the existing `method-based-validation` spec is unaffected)_

## Impact

- **src/Item.php**: Core changes — implicit id field handling, `ID_TYPE` constant, `assignId()` method, `setId()` rejection, adjustments to `getField()`, `getSqlValues()`, `getValues()`.
- **src/Repository.php**: `insertItem()` calls `assignId()` instead of `setId()` after INSERT.
- **tests/**: Existing tests that declare `'id'` in FIELDS need updating. Tests for `setId()` need updating to expect exceptions. New tests for `assignId()`, `ID_TYPE`, and implicit id behaviour.
- **examples/**: Remove `'id'` from FIELDS in all example Item classes.
- **README.md**: Update Item definition examples and field documentation.
- **No dependency changes.**
