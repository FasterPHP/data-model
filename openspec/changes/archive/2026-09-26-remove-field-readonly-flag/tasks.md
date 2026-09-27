## 1. Remove the flag from Field

- [x] 1.1 In `src/Field/Base.php`, remove the `$isReadonly` property, `setReadonly()` and `isReadonly()`, and remove the `$isReadonly` constructor parameter so the signature becomes `__construct(string $name, mixed $initialValue = null)`; change the explicit-initial-value test from `func_num_args() >= 3` to `func_num_args() >= 2`, and remove or correct the "bypasses readonly check" wording in the constructor's docblock and comment; verify by reading the file and confirming `grep -n -i 'readonly' src/Field/Base.php` returns nothing
- [x] 1.2 Delete `testSetReadonly()` and `testSetReadonlyToggleOff()` from `tests/FieldTest.php`, since they test only the removed methods; verify `grep -rn 'isReadonly\|setReadonly' src tests` returns nothing

## 2. Update Item

- [x] 2.1 In `src/Item.php`, stop computing `$isReadonly` during field construction and construct fields as `new $fieldClassName($fieldName, $initialValue)` or `new $fieldClassName($fieldName)`, preserving the existing distinction between an explicitly supplied initial value (including an explicit `null`) and none; remove the stale "bypasses readonly check" comment; verify with `vendor/bin/phpunit --no-coverage --filter ItemTest` and the implicit-id tests, which depend on the id field receiving an explicit `null`
- [x] 2.2 Add a docblock to `Item::FIELDS_READONLY` stating that such a field may be set on a new (temp) Item and becomes immutable once the Item has been persisted, and that enforcement is performed by `Item::setValue()`; verify by reading it against the `allow-readonly-set-on-temp-items` behaviour and the "State transition on setValue" requirement in `openspec/specs/item-state-model/spec.md`

## 3. Final verification

- [x] 3.1 Confirm Item-level protection is unchanged: readonly fields settable on a temp Item and rejected once persisted, and external and aggregate fields always rejected; verify the existing tests covering these pass unmodified, adding one per case if any is not already covered
- [x] 3.2 Confirm no remaining references anywhere outside the archive, including `README.md`, `examples/` and `docs/`; verify with `grep -rn 'isReadonly\|setReadonly' . --exclude-dir=vendor --exclude-dir=tools --exclude-dir=archive`
- [x] 3.3 Run `vendor/bin/phpcs` and confirm zero violations
- [x] 3.4 Run `vendor/bin/phpunit --no-coverage` and confirm the full suite passes with 445 tests: the 447 recorded when this change was planned, less the two deleted in 1.2
