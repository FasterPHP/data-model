## Why

`Repository::getComparison()` unconditionally prefixes unqualified column names with the base table name (e.g. `orders.totalAmount`). This is correct for regular fields in WHERE clauses, but aggregate fields are SELECT aliases (e.g. `SUM(amount) AS totalAmount`) that must appear bare in HAVING clauses. The table-qualification produces `Unknown column 'tableName.aggregateField' in 'HAVING'` errors whenever a caller filters by an aggregate field.

This is a regression introduced in commit `4f2132e` ("updated repository class to use Sql help"). The original `getArgsSqlAndParams()` had a three-way check for column qualification:

1. **Has a dot** → already qualified, backtick-escape as-is
2. **In FIELDS or FIELDS_READONLY** → qualify with table name (avoids ambiguity on JOINs)
3. **Else** → use bare backtick-quoted name (the alias/aggregate path)

When the method was refactored into `buildComparison()` (later renamed `getComparison()`), this was simplified to a two-way check (has dot vs. not), collapsing branches 2 and 3. The comment "Qualify column with table if no explicit alias provided" was carried over but the alias awareness was lost.

## What Changes

- Restore the original three-way qualification logic in `getComparison()`: only table-qualify fields that exist in `FIELDS` or `FIELDS_READONLY`; leave aggregate fields (and any other unrecognised keys) bare — matching the pre-refactor behaviour
- Fix existing tests that incorrectly assert table-qualified aggregate identifiers in HAVING output (e.g. `testHavingClauseUsedForAggregateFields` and `testMixedParamsSplitBetweenWhereAndHaving`)
- Add a new test that explicitly verifies aggregate fields are NOT table-qualified

## Capabilities

### New Capabilities

- `aggregate-field-qualification`: Correct identifier qualification for aggregate fields in HAVING clauses — aggregate fields (SELECT aliases) must not be prefixed with the base table name

### Modified Capabilities

## Impact

- `Repository::getComparison()` — single conditional change to skip table-qualification for aggregate keys
- `AggregateFieldsRepositoryTest` — fix two existing assertions that encode the bug, add new targeted test
- Downstream consumers (e.g. `gem` package's `Course\Participant\Repository`) will work correctly without workarounds — no API change required
