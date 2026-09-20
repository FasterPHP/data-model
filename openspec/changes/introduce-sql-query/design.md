## Context

See proposal.md - Why. The current state the design builds on:

`buildSelectSqlAndParams()` already exists as a coarse hook returning a `[$sql, $params]` tuple,
introduced in `fix-sql-safety-and-query-defects`. It composes `getSelectClause()`,
`getFromClause()`, `getGroupByClause()` and the WHERE and HAVING helpers, and merges the two
parameter sets through `mergeParams()`, which throws on collision. `fetchData()` then hands the SQL
and parameters to the shared paginator through `setSql()` and `setParams()`.

So the extension point is in the right place already. What it lacks is a type: the tuple's halves
are related only by convention, `mergeParams()` exists solely because a flat array cannot attribute a
binding to a clause, and the two-setter handoff to the paginator leaves a window in which SQL and
parameters disagree.

In a known consumer, six of eleven repositories override `getSelectClause()` and `getFromClause()`,
always together and always to express a join. Two others bypass the repository with
`$this->getPdo()->prepare()`.

## Goals / Non-Goals

**Goals:**

- Make it structurally impossible for a query's SQL and its parameters to be separated, merged
  lossily, or observed in a half-updated state.
- Give subclasses one coarse override that can express any SELECT, so escaping to raw PDO is never
  the only option.
- Leave the six existing clause overrides working untouched.

**Non-Goals:**

- Any change to INSERT, UPDATE or DELETE.
- A fluent builder, an expression model, or anything that generates SQL from a DSL. Clauses hold raw
  SQL strings.
- Migrating the consumer's two raw-PDO repositories. They keep working; moving them is a later, optional
  change.
- Removing the repository's shared paginator. Handing it a whole query closes the inconsistency
  window, which is the part that caused bugs; the sharing itself is left alone.

## Decisions

### Two types, not one

`SqlClause` holds a fragment and its parameters. `SqlQuery` holds named clauses. The alternative of a
single type holding parallel `sql` and `params` arrays was rejected: it reproduces the tuple's
problem one level up, and every operation on it would have to keep two arrays in step by hand.

Attributing parameters to clauses is what makes the collision check meaningful. `mergeParams()` can
currently only say that two bindings clash; per-clause parameters can say which clauses they came
from, which is what an implementor needs in the exception message.

### Immutable, with a single `with()` for derivation

`SqlQuery` is a `readonly` class. Replacing a clause returns a new instance.

This is deliberately inconsistent with the rest of the library, where `Sort`, `Paginator\Base` and
`SqlPaginator` are all mutable and fluent. That inconsistency is the point: the mutable-fluent style
is exactly what produced the stale-cache and half-updated-state defects already fixed once in this
package. A query object that a subclass can hand out and have modified behind its back would
reintroduce the same class of bug in a new place.

*Alternative considered*: per-clause named withers (`withWhere()`, `withHaving()`). Rejected as more
surface for no added safety; a single `with()` taking named arguments covers every case and reads
well at the call site.

### Absent is distinct from empty

A query distinguishes a clause that is not present from one whose fragment is an empty string. The
current code conflates them by testing `$sql !== ''` before appending a keyword, which works but
means a subclass cannot express "a WHERE clause that matches everything" separately from "no WHERE
clause". Making absence explicit removes the ambiguity and makes the render rules simple to state.

### `buildSelectSqlAndParams()` is removed rather than deprecated

It is replaced by `buildSelectQuery()`, which returns a `SqlQuery`.

*Alternative considered*: keeping it as a deprecated delegate. Rejected. If it delegates to the new
hook, a subclass that overrides it is silently bypassed, which is worse than an outright removal that
fails loudly at the override site. It shipped days ago, it is overridden by no known consumer, and a
protected method that looks like an extension point but no longer is has negative value. The same
reasoning applies to `mergeParams()`, whose job moves into query rendering.

Note the contrast with `getSelectClause()` and `getFromClause()`, which are kept indefinitely: those
have six real overrides, and the default `buildSelectQuery()` composes them precisely so that those
overrides keep working.

### SQL classes gather into a `Sql` namespace

`SqlQuery` and `SqlClause` are introduced at `Sql\SqlQuery` and `Sql\SqlClause` rather than at the
top level, and the existing `Sql` class moves alongside them as `Sql\SqlUtil`. Without this, the
change would add two more classes to an already flat `src/` whose SQL-related members would then be
spread across three levels of specificity.

The prefix is kept on each class name, so the short name in code stays unambiguous: `SqlQuery` rather
than `Query`, `SqlUtil` rather than `Util`. This departs from the library's dominant precedent, where
`Field\Base`, `Field\Integer` and `Paginator\Base` all drop the namespace prefix, but it matches
`Paginator\SqlPaginator`, and the alternative reintroduces exactly the vagueness that led to choosing
`SqlQuery` over a bare `Query` in the first place.

Deleting `src/Sql.php` in the same step as creating `src/Sql/` avoids any period in which a class and
a namespace share the name `FasterPhp\DataModel\Sql`. PHP tolerates that combination, but it is
confusing to read and there is no reason to pass through it.

### `Util` becomes `ClassNameUtil`

Every method on `Util` resolves a class name: `getItemClassName()`, `getSetClassName()`,
`getRepositoryClassName()` and the shared `getClassName()` they delegate to. The name `Util` invites
unrelated helpers to accumulate there, which is how a utility class stops being explicable. It stays
at the top level rather than joining the `Sql` namespace, because class-name resolution has nothing
to do with SQL.

*Alternative considered*: leaving both classes alone and adding the two new ones at the top level.
Rejected because the renames are cheap now and expensive later. Both are public, so each release that
ships them under the current names raises the cost of changing them, and this change is already
breaking the surface in a way consumers must absorb.

### The paginator gains a query-shaped entry point

`SqlPaginator` gets a way to be given a whole `SqlQuery`, replacing the `setSql()` then `setParams()`
sequence for this path. Its existing cache invalidation already discards results when the query
changes; handing it one value means the invalidation decision is made once against a coherent input
rather than twice against two.

The older setters are not removed in this change. They are part of the paginator's public surface and
have uses beyond the repository, so retiring them belongs with the wider paginator work rather than
here.

## Risks / Trade-offs

- **Removing `buildSelectSqlAndParams()` breaks an unknown subclass** → It was introduced days ago in
  the immediately preceding change and no consumer overrides it. Removal fails at compile-time
  visibility rather than silently, so any unknown case is immediately obvious rather than quietly
  wrong.

- **`readonly` queries are inconsistent with the library's mutable-fluent style** → Accepted
  deliberately, with the reasoning recorded above. The risk is that a future contributor "fixes" the
  inconsistency; the design note exists to prevent that.

- **A subclass composes an incoherent query, such as HAVING without GROUP BY** → Not prevented. The
  library lets subclasses write arbitrary SQL by design, and validating SQL semantics is not its job.
  The database reports it.

- **Two new classes in a library that values being small** → Both are value objects of a few dozen
  lines with no dependencies. They replace a tuple convention, a merge helper and an implicit
  contract, so the conceptual surface does not grow as much as the type count suggests.

- **Renaming two public classes breaks an unknown consumer** → The known consumers
  were searched on the refactor branches and reference neither `Util` nor `Sql`. A rename fails at
  autoload time rather than silently, so an unknown consumer sees a clear class-not-found error
  rather than wrong behaviour. Both renames are also cheaper now than after another release.

- **Hand-written queries bypass identifier validation** → A subclass writing its own SQL is trusted,
  exactly as it is today when it overrides `getSelectClause()`. Filter keys still pass through
  `getComparison()` and remain subject to `sql-identifier-safety`.

## Migration Plan

1. Move `src/Sql.php` to `src/Sql/SqlUtil.php` and `src/Util.php` to `src/ClassNameUtil.php`,
   updating every reference. Pure moves with no behavioural change, so the suite passing unchanged is
   the whole verification.
2. Add `SqlClause` and `SqlQuery` in the `Sql` namespace, with their tests. Nothing uses them yet, so
   this is purely additive.
3. Give `SqlPaginator` its query-shaped entry point, leaving the existing setters in place.
4. Replace `buildSelectSqlAndParams()` with `buildSelectQuery()`, and route `getDataWithParams()` and
   `fetchData()` through it. Remove `mergeParams()`.
5. Verify the six join overrides in the known consumer still produce identical SQL, by asserting the
   generated SQL for a repository shaped like theirs before and after.
6. Document the coarse hook and the hand-written query in `README.md` and `examples/`.

Each step is committed separately per the checkpoint discipline in `docs/standards/workflow-notes.md`.
Step 1 is a rename with no behavioural change; steps 2 and 3 are additive and independently
revertable; step 4 is the breaking one for the query hook.
