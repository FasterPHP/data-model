## Why

The recent validation refactor (commit `a179994`) replaced the `VALIDATORS` constant-based approach with `validate{FieldName}()` method discovery, but the README and all validation-related examples were not updated. The README also has pre-existing errors (e.g., `DB_NAME`/`TABLE_NAME` on Item instead of Repository, `FIELDS_READONLY` instead of `FIELDS_EXTERNAL` for joins, missing `ID_FIELD`). Anyone following the documentation will hit fatal errors or incorrect behavior.

## What Changes

- **README Quick Start**: Move `DB_NAME`/`TABLE_NAME` from Item to Repository, add `ID_FIELD`, mention magic getter/setter support via `__call`
- **README Joins section**: Replace `FIELDS_READONLY` with `FIELDS_EXTERNAL` for join fields
- **README Validation section**: Replace `VALIDATORS` constant examples with `validate{FieldName}()` method pattern using `ValidatableTrait` + `LaminasValidatorTrait`
- **README Custom Validators section**: Replace `buildValidatorChain()` example with the actual extension point (defining `validate{FieldName}()` methods returning any object with `isValid()`/`getMessages()`)
- **Example 01** (`01-basic-usage.php`): Add `ValidatableTrait` + `LaminasValidatorTrait`, replace `VALIDATORS` constant with `validate{FieldName}()` methods
- **Example 03** (`03-validation.php`): Add `ValidatableTrait` + `LaminasValidatorTrait`, replace `VALIDATORS` constant with `validate{FieldName}()` methods
- **Example 06** (rename `06-custom-validators.php` → `08-custom-validators.php`): Fix `StandardUserItem` to use traits + methods; fix `CustomUserItem` to use `validate{FieldName}()` returning a custom chain object (remove `buildValidatorChain`)
- **Example 06** (new `06-symfony-validation.php`): Concrete Symfony Validator integration with adapter class
- **Example 07** (new `07-laravel-validation.php`): Concrete Laravel Validator integration with adapter class
- **README Examples list and Custom Validators section**: Reference new framework examples; keep README validation section concise

## Capabilities

### New Capabilities

_(none — this is a documentation-only change)_

### Modified Capabilities

_(none — no spec-level behavior is changing, only documentation and examples)_

## Impact

- **Files modified**: `README.md`, `examples/01-basic-usage.php`, `examples/03-validation.php`, `examples/06-symfony-validation.php` (new), `examples/07-laravel-validation.php` (new), `examples/08-custom-validators.php` (renamed from `06-custom-validators.php`)
- **No code changes**: All changes are to documentation and example files only
- **No dependency changes**: No new packages required
- **User-facing**: Anyone reading the docs or examples will now get working, accurate instructions
