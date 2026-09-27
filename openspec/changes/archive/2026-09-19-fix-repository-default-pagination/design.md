## Context

See proposal.md - Why. The mechanism behind the defect is short: `Repository::__construct()` builds
`new SqlPaginator($pdo, $sort)` when the caller supplies no paginator, that paginator never has its
page size set, and `Paginator\Base::getMaxItemsPerPage()` therefore falls through to the static
default. Nothing in that chain is visible at the call site.

The decision to retain the static defaults was taken deliberately rather than by omission. A
factory would be the conventional answer, but without a container it has to be threaded through
every layer that creates a paginator, which means the library would be dictating the consuming
application's architecture. The existing arrangement is a static default with an explicit instance
override, which `paginator-instance-override` already specifies.

## Goals / Non-Goals

**Goals:**

- Make pagination something a caller asks for, so no query is silently truncated by configuration
  set elsewhere in the application.
- Keep the static defaults working exactly as they do for paginators the caller constructed.
- Ensure a single-item lookup costs one row, not a page, and not a whole table.

**Non-Goals:**

- Removing or replacing the static defaults.
- Changing `Repository`'s shared mutable paginator, or introducing the statement boundary. That is
  the `SqlQuery` change.
- Changing the `paginator-instance-override` precedence rules.

## Decisions

### Set the page size explicitly rather than bypassing the default lookup

The internal paginator has `setMaxItemsPerPage(null)` called on it at construction, rather than
`getMaxItemsPerPage()` gaining a special case for repository-owned paginators.

This reuses the precedence rule that already exists: an explicit instance value beats the static
default. `Repository` simply becomes one more caller that sets an explicit value, so no existing
requirement changes and there is no second mechanism to reason about. A special case inside the
getter would make the same paginator behave differently depending on who created it, which is
exactly the kind of invisible coupling this change is meant to remove.

*Alternative considered*: having `Repository` not construct a paginator at all until one is needed,
and treating the null case as unpaginated throughout. Rejected because `setSort()` and
`setMaxItemsPerPage()` on the repository both delegate to the paginator, so it would have to be
created lazily and every delegation would need a null check.

### A short-lived paginator for single-item lookups

`getItemWithParams()` builds its own `SqlPaginator` with a page size of one, uses it for that query,
and discards it. The repository's own paginator is not touched.

*Alternative considered*: setting the page size to one on the shared paginator and restoring it
afterwards. Rejected on two counts. It leaves the limit behind if the query throws, unless wrapped
in `try`/`finally`, and it discards the shared paginator's cached result figures as a side effect of
an unrelated read. It also deepens the shared-mutable-paginator problem that the `SqlQuery` change
exists to address, which is the wrong direction to move in the meantime.

The cost is one object per single-item lookup. `SqlPaginator` holds a PDO reference, a SQL string,
a parameter array and a sort map, so this is cheap relative to the query it wraps.

The new paginator inherits the repository's current sort, so `getItemWithParams()` on a sorted
repository still returns the first row under that sort. Without this the change would alter which
row a non-unique lookup returns.

### `getItemWithId()` is left to route through `getItemWithParams()`

It already delegates, so it inherits the one-row limit without its own code path. An id lookup
matches one row anyway, so the limit is redundant there, but a redundant `LIMIT 1` costs nothing and
having one implementation is worth more than avoiding it.

## Risks / Trade-offs

- **A consumer relied on bare repositories being implicitly paginated** → Queries that previously
  returned a page now return everything, which on a large table is a much heavier query. Neither
  known consumer does this, and each of the application's bare Set fetches was read and intends the full
  set. The mitigation for anyone who did rely on it is to pass a paginator, which is a one-line
  change at the construction site.

- **A large table fetched through a bare repository becomes a large result set** → This is the
  change working as intended rather than a defect, but it is worth stating plainly: the library no
  longer imposes an accidental safety limit. Callers that want a bound must now ask for one.

- **Which row a non-unique single-item lookup returns could shift** → The short-lived paginator
  inherits the repository's sort, so ordering is preserved. Where no sort is set the row returned is
  whatever the database yields first, which is true today as well.

- **Extra object allocation per single-item lookup** → Not material next to preparing and executing
  a query, and it replaces fetching and discarding up to a full page of rows.

## Migration Plan

1. Write characterisation tests recording that a bare repository currently inherits the static
   default, and that `getItemWithParams()` currently fetches a page, so the change is made against a
   recorded baseline.
2. Change the constructor, then the single-item lookup, each committed separately per the checkpoint
   discipline in `docs/standards/workflow-notes.md`.
3. Audit the consuming application's bare Set fetches. No code change is expected there, but
   its bulk child-record fetches should be confirmed as now returning every row.

Rollback is a plain revert: no data migration, no persisted state, no dependency change.
