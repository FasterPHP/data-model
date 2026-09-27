## Why

`buildSelectQuery()` is handed the caller's filters and trusted to apply them, and nothing checks
that it does. A hand-written query that leaves `$params` unused runs without complaint and returns
the wrong rows. The documented example does exactly that. Run against SQLite, the README's
`TicketRepository` returned ticket 1 for `getItemWithId(2)` and for `getItemWithId(3)`, and
`getSetWithParams(['assignedTo' => 2])` returned every open ticket. `examples/04-joins.php` has the
same flaw. Both examples were written by the author of the hook, which says the contract is easy to
get wrong, not that the examples were careless.

`getItemWithId()`, `getItemWithParams()` and `getSetWithParams()` pass their filters only through
the hook's parameters, so an override that forgets them silently turns an identity lookup into
"the first row of the query". That is the most damaging failure a data layer can have: no error, a
plausible result, and the wrong record written back if the caller then saves it.

The examples also teach the hook badly, independently of the defect:

- The README example aliases the base table (`tickets t`). The built-in filters qualify declared
  columns with the table name (`` `tickets`.`ticketId` ``), so even an override that did apply
  `$params` through the default helpers would fail with an unknown column.
- `new SqlQuery(...)` is called with three positional `SqlFragment`s. Nothing on the page says which
  is the select list, which the source and which the condition, and the `SELECT`, `FROM` and
  `WHERE` keywords are added by `render()`, out of the reader's sight.
- The README example demonstrates a join the string-returning clause hooks, documented immediately
  above it, already express. It does not show why the coarse hook exists.
- The joins example passes its threshold through a `$minSalary` property set just before the call,
  hidden state that a filter would express directly.

This is the right moment to change the contract. 1.0.0-rc1 is the first published release, the hook
was introduced recently, and no known consumer overrides it.

## What Changes

- **BREAKING**: `buildSelectQuery()` takes no arguments. It returns the repository's base query,
  independent of any particular retrieval. Its default implementation composes the select, from and
  group by clause hooks as today, without the filter clauses.
- The repository applies the caller's filters itself, after the hook returns. The WHERE filters from
  `getWhereSqlAndParams()` are ANDed onto the query's WHERE clause and the aggregate filters from
  `getHavingSqlAndParams()` onto its HAVING clause. A hook override, hand-written or derived, can no
  longer drop a caller's filters, so `getItemWithId()` returns the requested row or none.
- `getWhereSqlAndParams()` and `getHavingSqlAndParams()` keep their signatures and remain the place to
  inject or translate filters. A known consumer injects filters this way today and keeps working
  unchanged.
- `SqlQuery` gains `andWhere()` and `andHaving()`, which derive a query with a condition ANDed onto
  the existing clause, or set as the clause when there is none. Combined conditions are
  parenthesised so neither side's precedence can change the other's meaning, and a parameter bound
  to different values by the two sides is rejected rather than silently discarded.
- An unmodified repository, and one overriding only clause hooks, produces byte-for-byte the SQL it
  produces today.
- The README's "Replacing the Whole Query" section and `examples/04-joins.php` are rewritten. The new
  examples derive from the parent query with named arguments, show the SQL they render to, use a
  bound parameter inside a join (which the string-returning clause hooks cannot express), express
  per-call conditions as filters rather than repository state, and state the constraint that the
  base table must not be aliased.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `repository-query-hook`: the hook no longer receives filters; the repository applies them to
  whatever query the hook returns, so a hand-written query is filtered like a default one.
- `sql-query-construction`: a query can derive a new query with a condition ANDed onto its WHERE or
  HAVING clause, with the same parameter-collision guarantee as rendering.

## Impact

- `src/Repository.php`: `buildSelectQuery()` signature and default body; a private step applying
  filters, used by both Set and Item retrieval.
- `src/Sql/SqlQuery.php`: `andWhere()` and `andHaving()`.
- Tests: the `HookRepository` and `HandWrittenRepository` fixtures and the hook tests that assert the
  hook receives filters; new tests for filters on hand-written queries, identity lookup, clause
  combination and parameter collisions. The byte-for-byte SQL baselines in
  `RepositorySelectSqlTest` must pass unchanged.
- Documentation: `README.md`, `examples/04-joins.php`, and the `buildSelectQuery()` docblock.
- Consumers: no known consumer overrides `buildSelectQuery()`, so none needs a change. A subclass that
  does override it must drop the parameters from its signature, and PHP reports the mismatch when
  the class is loaded, so the break is loud rather than silent. Any filtering such an override did
  itself is then done by the repository instead.
- Release: ships as 1.0.0-rc2.
