## 1. Core Implementation

- [x] 1.1 Modify the `setValue()` guard in `src/Item.php` to allow `FIELDS_READONLY` on temp items: change the conditional so `FIELDS_READONLY` only throws when `!$this->isTemp()`, while `FIELDS_EXTERNAL` and `FIELDS_AGGREGATE` remain unconditionally rejected

## 2. Tests

- [x] 2.1 Update any existing tests that assert `setValue()` throws on readonly fields for temp items — these should now assert success
- [x] 2.2 Add test: setting a `FIELDS_READONLY` field on a temp item succeeds and the value is retrievable via getter
- [x] 2.3 Add test: setting a `FIELDS_READONLY` field on a CURRENT item throws an Exception containing "read-only field"
- [x] 2.4 Add test: setting a `FIELDS_READONLY` field on a MODIFIED item throws an Exception containing "read-only field"
- [x] 2.5 Add test: setting a `FIELDS_EXTERNAL` field on a temp item still throws
- [x] 2.6 Add test: setting a `FIELDS_AGGREGATE` field on a temp item still throws
- [x] 2.7 Run full test suite and confirm all tests pass
