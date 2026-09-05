## Why

A design review of the `feature/refactor` branch found nine defects in the query-building
code introduced by that branch, spanning SQL identifier safety, filter comparison
semantics, and paginator cache invalidation. `src/Sql.php` is new on this branch, so these
are being introduced by the work that is about to be merged to `main` rather than inherited
from it.

The most serious is that `Sql::ident()` does not escape embedded backticks, so any
identifier reaching it can break out of its quoting. Combined with `Repository::getComparison()`
accepting arbitrary filter keys as identifiers, and `Sort` performing no validation of its
sort field, the library places the entire burden of identifier safety on every caller and
fails unsafely when a caller forgets. These should be fixed before the branch is merged,
and separately from any architectural change.

## What Changes

**SQL identifier safety**

- `Sql::ident()` escapes embedded backticks by doubling them, so an identifier can no longer
  terminate its own quoting.
- `Sql::ident()` rejects identifiers that are not a plain or dot-qualified identifier shape,
  throwing an `Exception` rather than emitting unsafe SQL.
- `Sort` validates its sort field against the same identifier shape at construction, matching
  the existing validation of sort direction.
- **BREAKING (narrowing)**: filter keys and sort fields that are SQL expressions rather than
  identifiers are no longer accepted. No known consumer relies on this.

**Filter comparison semantics**

- **BREAKING**: `NOT_EQUALS` with a `null` value produces `IS NOT NULL`. It currently casts
  `null` to `''` and emits `!= ''`, which is wrong in both directions.
- **BREAKING**: an empty array value produces a fragment matching no rows. It currently
  produces `IS NULL`, which is the opposite of the conventional reading and is inconsistent
  with `Sql::expandIn()`, which already returns `1 = 0` for the empty case.
- `Sql::placeholder()` produces a unique placeholder per distinct key. It currently collapses
  every non-alphanumeric character to `_`, so `user.id`, `user_id` and `user-id` all yield
  `:user_id`, and the `+` union in `getArgsSqlAndParams()` then silently discards all but the
  first binding.

**Paginator cache invalidation**

- `numPages` is invalidated whenever the values it derives from change. `clearResults()`,
  `setMaxItemsPerPage()` and `setPageNum()` currently leave it stale, so a paginator reused
  after a page-size change reports a page count from the previous configuration.

**Internal coupling (no behaviour change)**

- The `STARTS`, `ENDS` and `CONTAINS` search-type constants move so that `Sql` no longer
  depends on `Repository`, with the existing `Repository` constants retained as aliases.

Explicitly out of scope, to be handled separately: removal of the static paginator defaults
(`Paginator\Base::$defaultMaxItemsPerPage` / `$defaultMaxPageLinks`), which are in active use
by consumers, and any change to the shared mutable `SqlPaginator` in `Repository::fetchData()`.

## Capabilities

### New Capabilities

- `sql-identifier-safety`: how SQL identifiers are escaped and validated wherever the library
  interpolates a caller-supplied name into SQL, covering `Sql::ident()`, `Repository::getComparison()`
  and `Paginator\SqlPaginator::getSortSql()` via `Sort`.
- `filter-comparison-semantics`: the SQL and bound parameters produced for each search type and
  value shape, covering null handling, array and empty-array handling, and placeholder uniqueness.
- `paginator-cache-invalidation`: which cached paginator values are discarded when the inputs
  they derive from change.

### Modified Capabilities

- `aggregate-field-qualification`: the requirement that keys outside `FIELDS` and `FIELDS_READONLY`
  are used as bare backtick-quoted identifiers is narrowed. Such keys SHALL still be used bare and
  unqualified, but SHALL now also be required to be a valid identifier shape, and are rejected
  otherwise. Table-qualification behaviour itself is unchanged.

## Impact

**Affected code**

- `src/Sql.php`: `ident()`, `placeholder()`, `likeWildcards()`.
- `src/Repository.php`: `getComparison()`, `getArgsSqlAndParams()`, search-type constants.
- `src/Sort.php`: `setSortField()`.
- `src/Paginator/Base.php`: `getNumPages()`, `setMaxItemsPerPage()`, `setPageNum()`.
- `src/Paginator/SqlPaginator.php`: `clearResults()`.

**Affected consumers**

`faster-php/gem` and `Gem/portal2` are the known consumers. Both allowlist filter keys and sort
fields at the application layer (`Action\Base::getSearchVars()` and `Action\Base::getSqlPaginator()`),
so neither is currently exposed to the identifier defects and neither should be affected by the
narrowing. `portal2` passes dot-qualified keys such as `users.userId` and `a.courseId`, which remain
valid. Its sole `NOT_EQUALS` use passes a non-null id, so the null-handling correction does not change
its behaviour. `getNumPages()` is used by `portal2`'s paginator template, which will benefit from the
invalidation fix.

**No dependency changes.** No new runtime dependencies; the library remains PDO-only.

**Documentation**: `README.md` and `examples/` reference the search-type constants and paginator API
and should be checked for any statement contradicted by the new semantics.
