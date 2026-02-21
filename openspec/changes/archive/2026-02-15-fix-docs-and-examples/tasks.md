## 1. README Quick Start Section

- [x] 1.1 Remove `DB_NAME` and `TABLE_NAME` from the Item class example; add `ID_FIELD` constant
- [x] 1.2 Remove explicit getter/setter methods from the Item class example; add a note that `__call` provides magic `get{Field}()`/`set{Field}()` methods and that explicit methods are optional (for IDE autocompletion)
- [x] 1.3 Add `DB_NAME` and `TABLE_NAME` to the Repository class example
- [x] 1.4 Add `ID_FIELD` to the ValidatedUserItem example in the validation section

## 2. README Joins Section

- [x] 2.1 Replace `FIELDS_READONLY` with `FIELDS_EXTERNAL` in the TicketItem join example

## 3. README Validation Section (Laminas)

- [x] 3.1 Replace the `VALIDATORS` constant example with `validate{FieldName}()` methods using `ValidatableTrait` + `LaminasValidatorTrait` (model after `tests/TestModel/ValidItem.php`)
- [x] 3.2 Show `createChain()` and `attachValidator()` helper usage in the example

## 4. README Custom Validators Section

- [x] 4.1 Replace the `buildValidatorChain()` example with a pattern showing `validate{FieldName}()` methods that return a custom chain object (any object with `isValid($value)` and `getMessages()`)
- [x] 4.2 Update the explanatory text to describe the duck-type contract instead of referencing `buildValidatorChain()`

## 5. Example 01 — Basic Usage

- [x] 5.1 Add `use ValidatableTrait` and `use LaminasValidatorTrait` to UserItem
- [x] 5.2 Add `use` imports for `ValidatableTrait`, `LaminasValidatorTrait`, and `Laminas\Validator`
- [x] 5.3 Replace `VALIDATORS` constant with `validateName()` and `validateEmail()` methods using `createChain()`/`attachValidator()`

## 6. Example 03 — Validation

- [x] 6.1 Add `use ValidatableTrait` and `use LaminasValidatorTrait` to UserItem
- [x] 6.2 Add `use` imports for `ValidatableTrait`, `LaminasValidatorTrait`, and `Laminas\Validator`
- [x] 6.3 Replace `VALIDATORS` constant with `validateName()`, `validateEmail()`, `validateAge()`, and `validateUsername()` methods using `createChain()`/`attachValidator()`

## 7. Example 06 — Custom Validators

- [x] 7.1 Add `use ValidatableTrait` and `use LaminasValidatorTrait` to StandardUserItem; add `use` imports
- [x] 7.2 Replace `VALIDATORS` constant on StandardUserItem with `validateName()` and `validateEmail()` methods
- [x] 7.3 Add `use ValidatableTrait` to CustomUserItem (without LaminasValidatorTrait)
- [x] 7.4 Remove `buildValidatorChain()` from CustomUserItem; replace with `validateName()`, `validateEmail()`, and `validateUsername()` methods that each build and return a `CustomValidatorChain`
- [x] 7.5 Remove the `VALIDATORS` constant from CustomUserItem
- [x] 7.6 Update the framework integration notes (echo statements at end of file) to describe the `validate{FieldName}()` extension point instead of `buildValidatorChain()`

## 8. Renumber existing custom validators example

- [x] 8.1 Rename `examples/06-custom-validators.php` to `examples/08-custom-validators.php`

## 9. New Example 06 — Symfony Validation

- [x] 9.1 Create `examples/06-symfony-validation.php` with a `SymfonyValidatorAdapter` class that wraps Symfony's `Validation::createValidator()->validate()` behind the `isValid($value)`/`getMessages()` duck-type contract
- [x] 9.2 Add a `SymfonyUserItem` using `ValidatableTrait` (no `LaminasValidatorTrait`) with `validate{FieldName}()` methods returning the adapter
- [x] 9.3 Add usage examples demonstrating valid and invalid inputs with Symfony constraints

## 10. New Example 07 — Laravel Validation

- [x] 10.1 Create `examples/07-laravel-validation.php` with a `LaravelValidatorAdapter` class that wraps Laravel's `Validator::make()` behind the duck-type contract
- [x] 10.2 Add a `LaravelUserItem` using `ValidatableTrait` with `validate{FieldName}()` methods returning the adapter
- [x] 10.3 Add usage examples demonstrating valid and invalid inputs with Laravel rules

## 11. README Updates

- [x] 11.1 Update the README custom validators section to reference the new framework examples instead of inline code
- [x] 11.2 Update the README examples list to include examples 06, 07, 08 with correct names and descriptions

## 12. Verification

- [x] 12.1 Review all README code snippets to confirm they match the actual `src/` API (no stale constant names, correct class hierarchy)
- [x] 12.2 Verify examples 01, 03, 06, 07, and 08 are syntactically valid PHP (`php -l`)
