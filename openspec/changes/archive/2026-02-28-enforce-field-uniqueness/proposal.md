## Why

A field can currently be defined in multiple field arrays (FIELDS, FIELDS_READONLY, FIELDS_EXTERNAL, FIELDS_AGGREGATE) without any error. When this happens, `getField()` silently resolves the type from the first match via null-coalesce and the `$isReadonly` flag may be incorrect — a writable field in FIELDS could also appear in FIELDS_READONLY, making it writable despite the duplicate. Additionally, the existing migration guard for the id field only checks FIELDS, not the other three arrays. Both are latent bug vectors that should fail fast with clear errors.

## What Changes

- Refactor field resolution in `Item::getField()` to use four `isset()` boolean variables, arithmetic addition for overlap detection, and a `match` expression for class name resolution — replacing the current null-coalesce chain and separate `$isReadonly` isset checks
- This is both more efficient (5 array lookups vs the current 7) and adds overlap detection that throws an `Exception` if a field appears in more than one array
- Expand the id field migration guard to check all four arrays (not just FIELDS), unified into the same flow via a `$isIdField` arm in the match expression
- Add test coverage for both overlap detection and the expanded id migration guard

## Capabilities

### New Capabilities

- `field-uniqueness-validation`: Enforce that each field name is defined in exactly one of FIELDS, FIELDS_READONLY, FIELDS_EXTERNAL, or FIELDS_AGGREGATE, and that the id field is not declared in any of them — with clear error messages on violation

### Modified Capabilities

## Impact

- `src/Item.php` — refactor `getField()` field resolution block (lines ~208-237) into unified boolean + match flow
- `tests/ItemTest.php` — add tests for field overlap detection and expanded id migration guard
- Test fixtures — add fixture Item subclasses with intentional field overlap and id-in-readonly scenarios
- No breaking changes for correctly-defined Item subclasses; subclasses with duplicate field definitions or id in any field array will now throw (which is the desired behaviour)
