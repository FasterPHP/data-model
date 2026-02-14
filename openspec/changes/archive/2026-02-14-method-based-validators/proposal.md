## Why

Validators are currently defined as nested arrays in a `VALIDATORS` class constant. Because constants are data rather than executable code, code coverage tools cannot report whether each field's validation logic is actually exercised by tests. This makes it impossible to know at a glance which validators are tested and which are not. The original rationale for using constants — early parsing benefits under Swoole/FrankenPHP worker mode — does not hold up: both constants and method bodies are compiled once by OPcache and persist identically across requests in worker mode.

## What Changes

- **BREAKING**: Replace the `VALIDATORS` class constant convention with `validate{FieldName}()` instance methods (e.g. `validateName()`, `validateAge()`). Each method builds and returns a `ValidatorChain` for its field.
- Refactor `ValidatableTrait::validate()` to discover validator methods by convention (`validate` + ucfirst field name) instead of iterating a `VALIDATORS` constant.
- Remove the complex config-parsing logic in `LaminasValidatorTrait::addValidator()` — the responsibility for building the chain moves into each `validate{FieldName}()` method, where it is explicit and type-safe.
- `LaminasValidatorTrait` may retain helper methods for common patterns (e.g. attaching a validator with a custom message) but will no longer drive the entire chain-building from a config array.
- Update all test fixtures (`ValidItem`, `SkipIfEmptyItem`, `CallbackValidatorItem`, etc.) to use the new method-based approach.

## Capabilities

### New Capabilities

- `method-based-validation`: Convention for defining field validators as `validate{FieldName}()` methods on Item subclasses, with automatic discovery by `ValidatableTrait` and per-method code coverage tracking.

### Modified Capabilities

_(none — no existing specs)_

## Impact

- **Breaking change** for any consuming code that defines a `VALIDATORS` constant on an Item subclass. Migration path: replace each entry in the constant with a corresponding `validate{FieldName}()` method.
- **Source files modified**: `src/Validation/ValidatableTrait.php`, `src/Validation/LaminasValidatorTrait.php`.
- **Test fixtures modified**: `tests/TestModel/ValidItem.php`, `tests/TestModel/SkipIfEmptyItem.php`, `tests/TestModel/CallbackValidatorItem.php`, `tests/TestModel/MissingClassItem.php`, `tests/TestModel/NoValidatorsItem.php`.
- **Test files modified**: `tests/ItemTest.php` (validation-related tests updated to reflect new API).
- **Dependencies unchanged**: `laminas/laminas-validator` remains an optional dependency — the trait still uses Laminas validator classes, just instantiated directly in methods rather than via config arrays.
