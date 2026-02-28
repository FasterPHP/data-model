## 1. Refactor field resolution in Item::getField()

- [x] 1.1 Replace the migration guard if-block (lines ~208-214) and the null-coalesce chain + isset `$isReadonly` block (lines ~227-237) with the unified boolean + match flow: four `isset()` booleans, overlap check (`> 1`), expanded id guard (`> 0`), `match(true)` expression, and `$isReadonly` derivation
- [x] 1.2 Run existing tests to confirm no regressions for correctly-defined Item subclasses

## 2. Test field overlap detection

- [x] 2.1 Add fixture Item subclass with a field defined in both FIELDS and FIELDS_READONLY
- [x] 2.2 Add test: accessing the overlapping field throws Exception with message indicating multiple field arrays
- [x] 2.3 Add fixture Item subclass with a field defined in three arrays (FIELDS, FIELDS_EXTERNAL, FIELDS_AGGREGATE) and test it throws

## 3. Test expanded id migration guard

- [x] 3.1 Add fixture Item subclass with id declared in FIELDS_READONLY and test it throws
- [x] 3.2 Add fixture Item subclass with id declared in FIELDS_EXTERNAL and test it throws
- [x] 3.3 Add fixture Item subclass with id declared in FIELDS_AGGREGATE and test it throws
- [x] 3.4 Verify existing test for id declared in FIELDS still passes (already covered by migration guard tests)
