## Context

See proposal.md for motivation.

`getComparison()` resolves a filter key to an identifier in three branches: a dotted key is used as
given; a key in `FIELDS` or `FIELDS_READONLY` is prefixed with `getTableName()`; anything else is
used bare. The placeholder is derived separately, from the key, by `SqlUtil::placeholder()`, so the
identifier and placeholder can change independently. The ID column is named by the Item's
`ID_FIELD`, is never in `FIELDS` (the Item rejects `id` there and the column is implicit), and is
selected by `getFieldList()` as `` `<table>`.`<ID_FIELD>` AS `id` ``. `getItemWithId()` passes
`<table>.<ID_FIELD>` as a dotted key, so it takes the first branch. The WHERE / HAVING split happens
earlier, by `FIELDS_AGGREGATE`, and does not look at qualification.

## Goals / Non-Goals

**Goals:**

- Make a filter on the ID column's bare name safe on joined repositories without callers having to
  qualify it.
- Make the reserved `id` key filter on the real column, so the README's example works on MySQL and
  MariaDB.
- Change no placeholder and no SQL that any existing test or known consumer produces.

**Non-Goals:**

- Sort fields. `Sort('courseId')` on a joined repository has the same ambiguity in ORDER BY, but
  sorting by `id` works there (ORDER BY may use select aliases), sort keys go through a different
  path, and qualifying them changes more SQL. It is worth its own change if a need appears.
- Write statements. INSERT, UPDATE and DELETE are single-table, so a bare ID column is unambiguous.
- Qualifying keys for hand-written queries that alias the base table; that remains documented as
  unsupported.

## Decisions

### 1. The ID column joins the "belongs to the base table" branch

The simplest correct rule is ownership: the ID column belongs to the base table as surely as a
declared field does, so it is qualified the same way. The check is added beside the `FIELDS` /
`FIELDS_READONLY` test rather than by adding the ID column to either array, which would change what
those arrays mean elsewhere (value lists, validation, persistence).

Alternative considered: leave qualification to callers and document it. This is today's situation,
and it fails only on MySQL-family databases, only on joined repositories, with an error far from its
cause.

### 2. The reserved `id` key maps to the qualified ID column

`id` is the Item's name for its ID and the key `getId()` reads, so a filter on `id` has only one
sensible meaning. Rendering it as `` `<table>`.`<ID_FIELD>` `` also fixes the select-alias problem,
since WHERE then references a real column. The mapping applies only when `ID_FIELD` is non-empty; an
Item without an ID column keeps today's behaviour. Where `ID_FIELD` is itself `id`, the result is the
same column, now qualified.

Alternative considered: reject `id` as a filter key with an exception pointing at `ID_FIELD`. Safer
in principle, but it breaks the documented example outright, and the key has no ambiguity to
protect against, because `id` cannot be a declared field.

### 3. Placeholders stay derived from the key as given

Only the identifier changes. `courseId` keeps binding `:courseId` and `id` keeps binding `:id`. This
keeps every existing baseline byte-identical and means a caller's parameter names do not move under
them. Deriving the placeholder from the qualified identifier instead was rejected: it would rename
placeholders to the hashed dotted form (`:courses_courseId_<hash>`) for no benefit.

### 4. Tests assert the identifier, not SQLite behaviour

SQLite accepts a select alias in WHERE and resolves some ambiguities differently, so an in-memory
behavioural test could pass while MySQL fails. The new tests assert the exact identifier and
placeholder produced, through `getComparison()` and through the executed SQL of a joined repository
using the recording PDO pattern already in the suite.

## Risks / Trade-offs

- [A table with a real column named `id` that is not its ID column] → Such a column cannot be a
  declared field (the Item rejects `id`), so it was never reachable as a filter on declared data; a
  caller who needs it can still use the dotted form `<table>.id`, which is used as given.
- [A consumer depending on a bare ID identifier] → None known. Bare output only works on a
  non-joined repository, where the qualified form is equivalent.

## Migration Plan

1. Ship as 1.0.0-rc3; the release notes describe the qualification rule and the `id` mapping.
2. Consumers qualifying ID keys by hand can keep doing so, or simplify at leisure.
3. Rollback is a plain revert.
