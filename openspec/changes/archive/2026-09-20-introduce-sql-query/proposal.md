## Why

`Repository` currently mixes three concerns: repository semantics (fetch an Item, fetch a Set,
persist, manage transactions), SQL construction, and PDO execution. The instinct was to extract the
SQL construction into a query builder, but a review of the code and of how consumers actually extend
it pointed somewhere else. The useful seam is between **constructing a query and executing it**, not
between the repository and SQL.

Two things follow from that, and both are visible in the current code and in a known consumer.

SQL and its parameters travel separately. `buildSelectSqlAndParams()` returns a `[$sql, $params]`
tuple whose halves are only related by convention, and `fetchData()` then feeds them to the paginator
through `setSql()` followed by `setParams()`, leaving a window in which the two disagree. The same
shape, applied to the paginator's cached results, produced the defects fixed in
`fix-sql-safety-and-query-defects`. Parameters from separate clauses are merged by
`mergeParams()`, which exists only because a flat parameter array cannot tell which clause a binding
came from.

There is no supported way to supply hand-written SQL. Of the 11 repositories in a known consumer, six
override `getSelectClause()` and `getFromClause()` to express a join, and two escape to
`$this->getPdo()->prepare()` directly because the clause hooks are too fine-grained to express the
query they need. That escape bypasses the repository entirely, including its sorting and its Item
construction.

## What Changes

- A `SqlClause` value object holds a SQL fragment together with the parameters that fragment binds.
  Parameters stop being a flat array detached from the SQL that uses them.
- A `SqlQuery` value object holds the clauses of a SELECT: select, from, where, group by and having.
  Every clause is readable from it and replaceable on it. It is immutable: `with()` returns a new
  instance rather than mutating, so a query handed to a subclass cannot be changed underneath the
  caller, and its SQL can never be temporarily out of step with its parameters.
- `Repository` gains `buildSelectQuery()` as the coarse extension point. Its default implementation
  composes the existing `getSelectClause()`, `getFromClause()`, `getGroupByClause()` and filter hooks
  exactly as today, so the six consumer repositories that override the clause hooks keep working
  unchanged.
- A subclass may instead return a wholly hand-written `SqlQuery` from `buildSelectQuery()`. This is
  the supported escape hatch that the two raw-PDO repositories currently lack, and unlike raw PDO it
  keeps sorting, pagination and Item construction.
- `fetchData()` accepts a `SqlQuery` rather than a SQL string and a parameter array.
- **BREAKING**: `buildSelectSqlAndParams()` is removed, replaced by `buildSelectQuery()`.
  `mergeParams()` is removed; per-clause parameters make it unnecessary. Neither is overridden by any
  known consumer, and both were introduced days ago in `fix-sql-safety-and-query-defects`.

SQL-related classes are also gathered into one place, since this change would otherwise add two more
of them to an already flat `src/`:

- `SqlQuery` and `SqlClause` are introduced in a new `Sql` namespace rather than at the top level.
- **BREAKING**: the existing `Sql` class moves into that namespace as `Sql\SqlUtil`. Its behaviour is
  unchanged; only its name and location change.
- **BREAKING**: `Util` is renamed `ClassNameUtil`. All four of its methods resolve class names
  (`getItemClassName()`, `getSetClassName()`, `getRepositoryClassName()` and the shared
  `getClassName()`), so the current name says less than it could about a class that is part of the
  published surface.

Explicitly not in this change: `insertItem()`, `updateItem()` and `deleteItemIds()` keep their
current shape. The write path is tightly coupled to Item lifecycle, no consumer is asking for it, and
the shape is better proven on reads first. There is no fluent builder and no expression objects:
clauses hold raw SQL strings, which is what keeps this a structured container for SQL rather than a
language for generating it.

## Capabilities

### New Capabilities

- `sql-query-construction`: what a `SqlQuery` and its clauses guarantee, covering clause
  addressability, immutability and derivation, and how a query's parameters relate to its SQL.
- `repository-query-hook`: the coarse extension point through which a repository's SELECT is built,
  and the guarantee that a hand-written query is a supported substitute for the default composition.

### Modified Capabilities

- `sql-identifier-safety`: its requirements are written in terms of `Sql::ident()`, which becomes
  `Sql\SqlUtil::ident()`. The behaviour required is unchanged; only the name the requirements refer
  to changes.

`filter-comparison-semantics` requires that every placeholder in generated SQL has a binding and that
WHERE and HAVING parameters are both preserved; per-clause parameters change how that is achieved but
not what is required. `aggregate-field-qualification` constrains `getComparison()`, which does not
move.

## Impact

**Affected code**

- `src/Sql/SqlQuery.php`, `src/Sql/SqlClause.php`: new.
- `src/Sql.php` moves to `src/Sql/SqlUtil.php`; `src/Util.php` moves to `src/ClassNameUtil.php`.
  Both are pure moves and renames with no behavioural change.
- `src/Repository.php`: `buildSelectQuery()` replaces `buildSelectSqlAndParams()`,
  `getDataWithParams()` and `fetchData()` take a `SqlQuery`, `mergeParams()` removed. It also
  references both renamed classes and its search-type constants alias `Sql\SqlUtil`.
- `src/Paginator/SqlPaginator.php`: gains a way to execute a `SqlQuery` atomically, so SQL and
  parameters arrive together rather than through two setters. It also references the renamed
  `Sql\SqlUtil` for identifier quoting.

**Affected consumers**

That consumer needs no change. The six repositories overriding `getSelectClause()` and
`getFromClause()` continue to work, because the default `buildSelectQuery()` composes those hooks.
The two repositories using raw PDO also continue to work, and become candidates for migration to a
hand-written `SqlQuery` in a later change; that migration is not required by this one.

Neither renamed class is referenced by any consumer on the refactor branches: the known
consumers were searched for `FasterPhp\DataModel\Util` and `FasterPhp\DataModel\Sql` and use
neither. The renames are therefore breaking to the published surface but not to any known caller.

**No dependency changes.** Two new classes, both small and both PDO-free.

**Documentation**: `README.md` and `examples/` describe the extension points and should gain the
coarse hook and the hand-written query alongside the existing clause overrides.
