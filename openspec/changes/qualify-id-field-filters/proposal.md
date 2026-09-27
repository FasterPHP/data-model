## Why

A filter on the Item's ID column is emitted as a bare identifier, so it breaks as soon as a
repository joins another table that has a column of the same name. `getComparison()` qualifies a key
with the table name only when the key is in `FIELDS` or `FIELDS_READONLY`, and the ID column is in
neither: it is implicit, named by `ID_FIELD`. On a repository whose from clause joins `modules`,
`getSetWithParams(['courseId' => 3])` renders `` WHERE `courseId` = :courseId ``, which MySQL and
MariaDB reject as ambiguous. A downstream audit found several joined repositories in a known
consumer where a bare ID filter would fail this way. The consumer avoids it by qualifying keys by
hand, which works, but it relies on every caller knowing the library does not do it for them.
`getItemWithId()` is unaffected only because it already passes the key qualified.

The same code path mishandles a filter keyed on the Item's reserved `id` (`ID_INTERNAL`), which the
README documents (`getSetWithParams(['id' => [1, 2, 3, 4, 5]])`). `id` exists only as an alias in
the select list, and MySQL and MariaDB do not allow a select alias in WHERE, so for every Item whose
ID column is not literally named `id`, the documented example fails with an unknown-column error.
SQLite does accept aliases in WHERE, which is why the in-memory examples never exposed it.

## What Changes

- A filter keyed on the Item's `ID_FIELD` is qualified with the repository's table name, exactly as
  a filter on a declared field is.
- A filter keyed on `ID_INTERNAL` (`id`) is resolved to the ID column, qualified with the table name,
  so it filters on the real column rather than on a select alias. `id` cannot be declared as a field
  (the Item rejects it), so the key has no other meaning it could clash with.
- Placeholder names are unchanged: they continue to be derived from the key as given. A filter on
  `courseId` still binds `:courseId`.
- Dot-qualified keys, `FIELDS_AGGREGATE` keys and `FIELDS_EXTERNAL` keys are unchanged, and the
  WHERE / HAVING split is unchanged.
- The README states the qualification rule, and the `id` filter example becomes correct on MySQL
  and MariaDB.

No known consumer relies on an ID filter being emitted bare, and no existing SQL baseline in the
suite filters on a bare ID key (every one goes through `getItemWithId()`, which is already
qualified), so this is additive for everything that works today.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `aggregate-field-qualification`: the rule deciding which filter keys are table-qualified gains the
  ID column, under its own name and under the reserved `id`.

## Impact

- `src/Repository.php`: `getComparison()`.
- Tests: new cases for ID-field and `id` keys on plain and joined repositories, asserting the exact
  identifier and unchanged placeholder; a MySQL-shaped assertion rather than reliance on SQLite,
  which accepts the alias.
- Documentation: `README.md` filtering section.
- Consumers: a caller already qualifying ID keys by hand keeps working, since dot-qualified keys are
  used as given. A caller relying on a bare `id` key today works only where the ID column is named
  `id`, and gets the same column, now qualified.
- Release: ships as 1.0.0-rc3.
