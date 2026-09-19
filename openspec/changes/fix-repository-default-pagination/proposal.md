## Why

A repository constructed without a paginator builds one internally, and that internal paginator
inherits the application-wide static default page size. The result is that every Set fetched from
a repository the caller did not explicitly paginate is silently truncated to the application's
page size, with no indication that rows were dropped.

In the known consumer this is not theoretical. It sets a default of 15 and constructs
repositories 81 times with a bare PDO against 5 times with an explicit paginator. Of its 23
`getSet*` call sites, only the 5 paginated list screens pass a paginator, so roughly 18 Set fetches
are capped at 15 rows today. Most are harmless because the real row counts are small, but
one service fetches a parent record's children this way, so a parent with
more than 15 children would be processed against only the first 15.

The static defaults themselves are being kept deliberately: requiring an injected factory would
force a dependency injection container on every consuming application, which is too much ceremony
for a library of this size. The defect is not that the defaults are static, it is that they reach
paginators nobody asked for.

## What Changes

- **BREAKING**: a paginator constructed internally by `Repository`, because the caller passed a
  `Sort` or passed nothing, SHALL be unlimited. Application-wide static defaults no longer reach it.
  Sets fetched from such repositories return every matching row instead of the first page.
- A paginator passed explicitly to `Repository` is untouched, so it continues to pick up the static
  defaults exactly as it does now. Pagination becomes opt-in by constructing a paginator.
- `getItemWithParams()` fetches at most one row, using a short-lived paginator of its own so the
  repository's paginator is not mutated. It currently fetches up to a full page and discards all but
  the first row.

Out of scope: removing or replacing the static defaults, and any change to the shared mutable
paginator held by `Repository`. The latter is part of the forthcoming `SqlQuery` work.

## Capabilities

### New Capabilities

- `repository-paginator-defaults`: which paginator a repository uses, and whether application-wide
  static defaults apply to it, depending on whether the caller supplied one.
- `single-item-retrieval-limit`: how many rows a single-item lookup fetches, independent of how the
  repository is paginated.

### Modified Capabilities

None. `paginator-instance-override` already specifies that an explicit value on an instance takes
precedence over the static default; this change only makes `Repository` one of the callers that sets
an explicit value. That requirement is unchanged.

## Impact

**Affected code**

- `src/Repository.php`: the constructor's internal `SqlPaginator` construction, and
  `getItemWithParams()`.

**Affected consumers**

The consuming application is the one to audit. Its 5 paginated list screens pass explicit paginators and are
unaffected. Its remaining Set fetches change from at most 15 rows to all matching rows, which is what
each of those call sites plainly intends. The bulk child-record fetch is the one where the current
truncation is most likely to have been doing real damage. No consumer code change is required, but each
bare Set fetch is worth eyeballing for anywhere the cap was load-bearing by accident.

Applications that relied on bare repositories being implicitly paginated will need to pass a paginator
explicitly. No such usage exists in the known consumers.

**No dependency changes.** No new runtime dependencies.

**Documentation**: `README.md` and `examples/` describe the paginator defaults and should be checked
for any statement that implies repository-internal paginators inherit them.
