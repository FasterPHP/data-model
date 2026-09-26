## Why

`Sql\SqlClause`, introduced in `introduce-sql-query`, is named for one of its uses rather than for
what it is. It holds any SQL text together with the parameters that text binds, and it is used at
two different scales: each slot of a `SqlQuery` holds one, and `SqlQuery::render()` returns one
containing the complete statement. A value holding a whole `SELECT ... FROM ... WHERE ...` statement
is not a clause, and the name implies it is.

The mismatch has already produced an inaccuracy. The constructor documents `$sql` as "the SQL
fragment, without its introducing keyword", which is false for the value `render()` returns, since
that begins with `SELECT`. The file header calls it a "SQL clause value object" while the class
description calls it "A SQL fragment together with the parameters that fragment binds". The code
already describes itself as a fragment; only the name disagrees.

The class is public, but it has not been merged beyond `feature/refactor` and no consumer references
it, so this is the cheapest point at which it will ever be renamed.

## What Changes

- **BREAKING**: `Sql\SqlClause` is renamed `Sql\SqlFragment`. Behaviour is unchanged.
- The class and constructor docblocks are corrected so they describe a fragment of any scale,
  including a complete statement, rather than a keyword-less clause.
- Every reference in `src/`, `tests/`, `examples/` and `README.md` is updated, and
  `tests/Sql/SqlClauseTest.php` is renamed to match its subject.

`SqlFragment` was chosen over the alternatives considered:

- `Sql` would give `Sql\Sql`, reuse the exact name of the static helper that became `Sql\SqlUtil`
  for an unrelated concept, and drop the noun, so a signature such as `render(): Sql` would no
  longer say the value carries parameters.
- `BoundSql` or `ParameterisedSql` name the defining property most precisely, but break the `Sql`
  prefix convention shared by `SqlQuery` and `SqlUtil`.
- Introducing a separate type for `render()`'s output was rejected: the rendered value behaves
  identically to a slot value, so a second type would exist only for naming.

"Fragment" is also exact for `render()`'s output in the case that matters for composition: a rendered
query used as a subquery in another query's `from` is, literally, a fragment of the outer statement.

Not in scope: the word "clause" where it genuinely means a clause. The `SqlQuery` slots, the
`getSelect()` / `getWhere()` accessors, and the `getSelectClause()` / `getFromClause()` repository
hooks all refer to real clauses and keep their names.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

None. No spec names the class. `sql-query-construction` already requires that a clause "SHALL hold a
SQL fragment together with the parameters that fragment binds", which the rename makes the code match
more closely rather than less. This change sets `skip_specs: true` accordingly.

`design.md` is not included: this is a rename with no architectural, dependency, security,
performance or migration dimension, and the only decision, the choice of name, is recorded above.

## Impact

**Affected code**

- `src/Sql/SqlClause.php` becomes `src/Sql/SqlFragment.php`, with corrected docblocks.
- `src/Sql/SqlQuery.php`: 34 references, in constructor and accessor signatures, `with()`,
  `render()` and its private helpers, and docblocks.
- `src/Repository.php`: the import and the five constructions in the default `buildSelectQuery()`.
- Tests: `tests/Sql/SqlClauseTest.php` (renamed), `tests/Sql/SqlQueryTest.php`,
  `tests/Paginator/SqlPaginatorQueryTest.php`, `tests/RepositoryHandWrittenQueryTest.php`.
- Documentation: `README.md` and `examples/04-joins.php`.

**Affected consumers**: none. No known consumer references the class.

**Archived changes are not edited.** `introduce-sql-query` in the archive refers to `SqlClause`, which
is an accurate record of what that change introduced.

**No dependency changes.**
