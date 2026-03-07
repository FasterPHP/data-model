## Context

`Repository::getComparison()` builds SQL comparison fragments for WHERE and HAVING clauses. It currently uses a two-way check to decide whether to table-qualify a column name: if the key contains a dot, use it as-is; otherwise, always prefix with `getTableName()`. This breaks HAVING clauses because aggregate fields are SELECT aliases (e.g. `SUM(amount) AS totalAmount`) and must not be table-qualified.

Prior to commit `4f2132e`, the original `getArgsSqlAndParams()` used a three-way check that only table-qualified fields found in `FIELDS` or `FIELDS_READONLY`. All other fields (including `FIELDS_EXTERNAL` and `FIELDS_AGGREGATE`) were left bare. The refactor collapsed this to a two-way check, losing the distinction.

## Goals / Non-Goals

**Goals:**
- Restore correct column qualification in `getComparison()` so aggregate and external fields are not table-prefixed
- Fix existing tests that assert the buggy table-qualified behaviour
- Add a targeted test proving aggregate fields are bare in HAVING output
- Follow TDD: write/update failing tests first, then fix `getComparison()`

**Non-Goals:**
- Refactoring `getComparison()` beyond the qualification fix
- Adding new query-building features
- Changing how `getWhereSqlAndParams()` / `getHavingSqlAndParams()` split params (this already works correctly)

## Decisions

### Restore positive membership check (not negative FIELDS_AGGREGATE check)

The fix will check whether the key exists in `FIELDS` or `FIELDS_READONLY` (the two field sets that belong to the base table). Only those get table-qualified. Everything else is left bare.

**Alternative considered:** Check `array_key_exists($key, FIELDS_AGGREGATE)` to skip qualification. Rejected because this would still incorrectly table-qualify `FIELDS_EXTERNAL` fields and wouldn't match the proven original logic. The positive check is more robust — it only qualifies what it knows belongs to the base table.

### Use `Sql::ident()` for bare keys

Bare keys will still be passed through `Sql::ident()` for backtick-escaping (e.g. `` `totalAmount` ``), just without the table prefix. This matches how the original code used manual backtick-wrapping.

## Risks / Trade-offs

- **Low risk of false negatives**: A field not in FIELDS, FIELDS_READONLY, or dot-qualified will be left bare. If a caller passes a typo'd field name, it won't be table-qualified. This matches the original pre-refactor behaviour and is acceptable — the resulting SQL error clearly identifies the issue.
