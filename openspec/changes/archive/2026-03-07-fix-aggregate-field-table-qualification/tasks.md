## 1. Add failing tests (TDD red phase)

- [x] 1.1 Add `testAggregateFieldNotTableQualified` to `AggregateFieldsRepositoryTest` — call `getComparison()` via reflection with an aggregate field key (`totalAmount`), assert the SQL fragment contains `` `totalAmount` `` but NOT `` `orders`.`totalAmount` ``
- [x] 1.2 Add `testExternalFieldNotTableQualified` — use `ExternalRepository` to call `getComparison()` with an external field key, assert bare identifier without table prefix
- [x] 1.3 Add `testReadonlyFieldIsTableQualified` — use `ReadonlyRepository` to call `getComparison()` with a readonly field key, assert it IS table-qualified
- [x] 1.4 Add `testRegularFieldIsTableQualified` — call `getComparison()` with a regular FIELDS key (`userId`), assert table-qualified
- [x] 1.5 Add `testDotQualifiedKeyUsedAsIs` — call `getComparison()` with `a.createdDate`, assert `` `a`.`createdDate` ``
- [x] 1.6 Run tests and confirm the aggregate and external tests fail (red)

## 2. Fix getComparison() qualification logic

- [x] 2.1 Restore three-way check in `Repository::getComparison()`: (1) dot-qualified → as-is, (2) in FIELDS or FIELDS_READONLY → table-qualify, (3) else → bare `Sql::ident($key)`

## 3. Fix existing tests that assert buggy behaviour

- [x] 3.1 Update `testHavingClauseUsedForAggregateFields` — change assertion from `` `orders`.`totalAmount` `` to `` `totalAmount` ``
- [x] 3.2 Update `testMixedParamsSplitBetweenWhereAndHaving` — change HAVING assertions from `` `orders`.`totalAmount` `` and `` `orders`.`orderCount` `` to bare forms

## 4. Verify

- [x] 4.1 Run full test suite and confirm all tests pass (green)
