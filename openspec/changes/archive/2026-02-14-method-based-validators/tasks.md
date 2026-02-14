## 1. Refactor ValidatableTrait

- [x] 1.1 Rewrite `validate()` to iterate `static::FIELDS` keys and call `validate{FieldName}()` via `method_exists()` instead of reading a `VALIDATORS` constant
- [x] 1.2 Remove the `buildValidatorChain()` abstract method declaration
- [x] 1.3 Verify `isValid()`, `getValidationErrors()`, and validation state reset (`unset($this->isValid)` in `Item::setValue()`) remain unchanged

## 2. Refactor LaminasValidatorTrait

- [x] 2.1 Remove `buildValidatorChain()` and `addValidator()` methods
- [x] 2.2 Add `createChain(): ValidatorChain` helper that returns a new empty `ValidatorChain`
- [x] 2.3 Add `attachValidator(ValidatorChain $chain, ValidatorInterface $validator, ?string $message = null, ?bool $breakOnFailure = null, ?int $priority = null): void` helper
- [x] 2.4 Remove `use ValidatableTrait;` from `LaminasValidatorTrait` — the two traits are now independent (Item classes use both separately)

## 3. Migrate test fixtures

- [x] 3.1 Convert `ValidItem::VALIDATORS` to `validateName()` and `validateAge()` methods using convenience helpers
- [x] 3.2 Convert `SkipIfEmptyItem::VALIDATORS` to a `validateName()` method with an early-return guard (`if (empty(...)) return new ValidatorChain`)
- [x] 3.3 Convert `CallbackValidatorItem::VALIDATORS` to a `validateName()` method using `Validator\Callback` directly
- [x] 3.4 Remove `MissingClassItem` — the "missing class" error path no longer exists (no config parsing)
- [x] 3.5 Update `NoValidatorsItem` — remove `VALIDATORS` constant if present; keep using both traits with no `validate*` methods

## 4. Update tests

- [x] 4.1 Update `testValidateFalse` and `testValidateTrue` in `ItemTest.php` — should pass unchanged since the public API (`isValid()`, `getValidationErrors()`) is the same
- [x] 4.2 Replace `testValidatorMissingClass` with a test that verifies fields without a `validate*` method are silently skipped
- [x] 4.3 Update `testValidatorSkipIfEmpty` and `testValidatorSkipIfEmptyNotSkipped` to work with the new method-based `SkipIfEmptyItem`
- [x] 4.4 Update `testCallbackValidatorPass` and `testCallbackValidatorFail` to work with the new method-based `CallbackValidatorItem`
- [x] 4.5 Verify `testValidateWithNoValidatorsConst` still passes with `NoValidatorsItem`

## 5. Verify

- [x] 5.1 Run `vendor/bin/phpunit --no-coverage` — all tests pass
- [x] 5.2 Run `vendor/bin/phpunit --coverage-text` — `ValidatableTrait` and `LaminasValidatorTrait` at 100%, overall ≥ 95%
- [x] 5.3 Verify each `validate*` method on test fixtures appears individually in the coverage report
