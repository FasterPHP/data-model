## Context

See proposal.md for motivation.

Today both retrieval routes call the hook with the filters: `getDataWithParams()` for Sets and
`getItemWithParams()` for Items, the latter through a one-row paginator of its own. The default
`buildSelectQuery($params, $types)` turns the filters into WHERE and HAVING fragments through
`getWhereSqlAndParams()` and `getHavingSqlAndParams()`, which split them by `FIELDS_AGGREGATE`.
`getComparison()` qualifies a declared field with `getTableName()`, and `getItemWithId()` passes its
key already qualified as `<table>.<idField>`. A known consumer overrides `getWhereSqlAndParams()` to
merge in filters the caller did not supply. No known consumer overrides `buildSelectQuery()`.

`RepositorySelectSqlTest` holds byte-for-byte baselines of the SQL generated before the hook existed,
including a joined repository that injects filters through the where hook. Those baselines are the
compatibility guarantee for everyone who does not override the hook.

`SqlQuery` already rejects, at render time, a parameter bound to different values by two clauses.
Filters use placeholders derived from the filter key by `SqlUtil::placeholder()`, so filtering on
`status` binds `:status`.

## Goals / Non-Goals

**Goals:**

- Make it impossible for a hook override to drop the caller's filters, without adding any step a
  subclass author has to remember.
- Keep the SQL of every repository that does not override the hook byte-for-byte unchanged.
- Keep the where and having hooks as the place to inject or translate filters.
- Leave the documentation's examples correct, runnable and self-explanatory.

**Non-Goals:**

- Letting a hand-written query alias its base table. Filters and identity lookups continue to
  qualify declared fields with the table name (see Decision 6).
- Pushing filters inside a derived table or subquery of a hand-written query. Filters constrain the
  outer query only.
- Any change to sorting, pagination, Item construction or the write path.

## Decisions

### 1. The hook takes no arguments; the repository applies filters afterwards

`buildSelectQuery()` becomes a zero-argument method returning the base query. A private step in
`Repository` takes the hook's result and the caller's filters, obtains the WHERE and HAVING fragments
from the existing where and having hooks, and ANDs them onto the query. Both retrieval routes go
through one private method (`buildRetrievalQuery($params, $types)` or similar) that calls the hook
and then that step, so there is still exactly one route from filters to SQL.

The step is private rather than protected. The whole point is a guarantee that holds regardless of
what a subclass overrides, and a protected step could be overridden away. A subclass can still
return an empty fragment from the where hook, but that is an explicit, visible choice in a method
whose job is filtering, not an omission in a method whose job is something else.

Alternatives considered:

- *Keep the signature and fix only the documentation.* This leaves the trap in place: every future
  override must remember to apply `$params`, and the failure when it does not is silent.
- *Keep passing the filters to the hook and also apply them afterwards.* The hook could then use the
  filters for other purposes and could not drop them. But it invites overrides that apply the
  filters themselves too, producing duplicated conditions, and it keeps suggesting that applying them
  is the hook's job. A hook with no arguments states the contract in its signature.
- *Detect an override that ignored `$params`.* There is no reliable way to tell from a returned
  query whether a given filter was honoured.

### 2. `SqlQuery::andWhere()` and `andHaving()` do the combining

Combining lives on the value object, not in the repository. That keeps the parameter-collision check
next to the parameters it protects, lets it run at derivation time (before any SQL is executed)
rather than only at render time, and gives hand-written code the same tool. Two named methods match
the named-clause style of `with()` better than a generic `and(string $clause, ...)`.

When the clause is absent, the condition becomes the clause exactly as given, with no parentheses.
When it is present, the result is `(<existing>) AND (<condition>)`. Grouping only when combining is
what keeps the default query's SQL byte-for-byte unchanged: the default hook returns no WHERE or
HAVING, so the filters become those clauses verbatim, as they are today. Always parenthesising was
rejected because it would change every repository's SQL for no gain. Never parenthesising was
rejected because `a OR b` ANDed with `c` would silently become `a OR (b AND c)`.

A condition with empty SQL and no parameters returns an equal query, so the repository can pass
whatever the where and having hooks return without special-casing "no filters". A condition with
empty SQL but with parameters is a contradiction (bindings with nowhere to bind) and throws.

### 3. Parameter collisions between the query and the filters throw

A hand-written query binding `:status` collides with a filter on `status` if the values differ. It
throws, naming the parameter, as a render-time collision already does. Renaming filter placeholders
automatically was rejected because it would change the SQL of every repository, breaking the
baselines. The documentation recommends that a hand-written query name its own placeholders so that
they cannot look like field names (for example `:q_visibility`), which makes collisions impossible in
practice.

### 4. Aggregate filters go to HAVING, as today

The split by `FIELDS_AGGREGATE` is unchanged; only the point at which it is applied moves. A
hand-written query for an Item class that declares aggregate fields must supply its own GROUP BY, as
the default query does through `getGroupByClause()`. This is documented rather than enforced, because
a hand-written GROUP BY may legitimately differ from the default one.

### 5. Per-call variation is expressed as filters, not repository state

Because the hook has no arguments, the base query cannot vary per call except through repository
state. The documentation says so directly: the base query describes what the repository selects, and
anything that varies per call is a filter. The joins example's `$minSalary` property is replaced by a
`GREATER_OR_EQUALS` filter on salary. Parameters bound inside the base query itself are for fixed
values, such as a visibility inside a derived table.

### 6. The base table must be exposed under its own name

Filters on declared fields, and `getItemWithId()`, qualify columns with `getTableName()`. A
hand-written query that aliases the base table breaks them with an unknown-column error. That error
is loud, so it is documented rather than engineered around. A hook letting a repository declare a
table alias for filter qualification was considered and deferred: it adds API surface for a need no
known consumer has, and it can be added later without breaking anything.

### 7. Documentation examples are chosen to show why the coarse hook exists

The README example derives from the parent query with named arguments and replaces the select list
and from source, adding a comment-count derived table that binds `:q_visibility`. The string-returning
clause hooks cannot carry that parameter, so the example shows the coarse hook doing something the
fine hooks cannot. The rendered SQL is shown beneath it, including a caller filter appearing as
WHERE, so the reader can see where each fragment ends up. The section states the rules: the query
must not depend on the call, per-call conditions are filters, the base table keeps its name, and a
query for aggregate fields supplies its own GROUP BY.

`examples/04-joins.php` keeps its department-average derived table, now with a bound parameter
inside it, and drops the `$minSalary` state in favour of a filter. It must run end to end against
in-memory SQLite, as it does today.

## Risks / Trade-offs

- [A subclass overriding the hook breaks at class load] → Intended: an incompatible signature is a
  fatal error when the class is loaded, not a silent change in results. No known consumer overrides
  the hook, and the change ships in a release candidate.
- [Filters can no longer be placed inside a derived table of a hand-written query] → Accepted as a
  non-goal. Such a query can take its inner condition from a fixed parameter, or the repository can
  expose a dedicated method. No known consumer needs this.
- [The alias constraint is only documented] → The failure is an immediate SQL error, not wrong
  results. The deferred alias hook remains available as a compatible addition.
- [Tests reach the hook by reflection with filters] → Those tests move to the private retrieval
  method, which is what they were really exercising. The baselines keep their assertions unchanged,
  which is the check that nothing else moved.

## Migration Plan

1. Ship as 1.0.0-rc2. The release notes flag the breaking signature change and the one-line
   migration: drop the parameters from an override, and stop applying filters in it.
2. Known consumers need no change. Their overrides of the clause hooks and of the where hook keep
   working, and the byte-for-byte baselines cover both patterns.
3. Rollback is a plain revert. There is no persisted state or data migration.
