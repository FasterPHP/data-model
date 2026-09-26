## Context

See proposal.md - Why. The shape of the current code that the design works with:

`saveItem()` and `saveSet()` each wrap their work in `if ($useTransaction) beginTransaction()`,
`commit()` on success, and `rollBack()` in a `catch (\Throwable)` that rethrows. Inside,
`insertItem()` executes the INSERT, reads `lastInsertId()` and calls `markItemPersisted($newId)`;
`updateItem()` executes the UPDATE and calls `markItemPersisted()`. `deleteItemIds()` does not touch
Item state. All four are protected, and no known consumer overrides any of them.

## Goals / Non-Goals

**Goals:**

- `$useTransaction` composes with a transaction the caller already holds, so several repositories can
  take part in one atomic write.
- After a transaction the repository owns, Item state matches what the database actually holds.
- No public signature changes.

**Non-Goals:**

- Restoring Item state after a caller rolls back its own transaction. The library cannot observe a
  transaction it did not begin, so this is documented as the caller's responsibility.
- Savepoints or partial rollback within a caller's transaction.
- Batching inserts or updates into multi-row statements.
- Wrapping any consumer write sequence in a transaction. The fix makes that safe; doing it is a
  separate decision.

## Decisions

### Ownership is decided once, at the start of the save

Each save computes whether it owns the transaction before doing anything else:

```php
$ownsTransaction = $useTransaction && !$this->pdo->inTransaction();
```

Every later decision to begin, commit, roll back, or defer the persisted mark uses that one value.
Checking `inTransaction()` again at commit or rollback time would be wrong: by then it reports the
repository's own transaction as active, so the answer no longer distinguishes "I began this" from
"I found this".

### Joining, not savepoints, when a transaction is already active

A save inside a caller's transaction runs as part of it: no begin, no commit, and no rollback on
failure. The exception propagates and the owner decides. This matches the default propagation most
persistence layers use (join if present, create if absent), and it keeps the rule to one sentence.

*Alternative considered*: an inner `SAVEPOINT`, with `ROLLBACK TO SAVEPOINT` on failure, so the inner
save undoes only its own writes and the caller's transaction continues. Rejected. It gives callers a
partial-failure state they then have to reason about, it needs raw savepoint SQL issued outside PDO's
transaction API, and nothing in either consumer wants partial rollback. A caller that does want it can
issue savepoints itself around a save with `$useTransaction` false.

*Alternative considered*: throwing a clearer exception when `$useTransaction` is true inside an active
transaction. Rejected because it makes the flag unusable in exactly the multi-table case that most
needs it.

### Defer the persisted mark through a queue, not by changing signatures

Rather than calling `markItemPersisted()` directly, `insertItem()` and `updateItem()` hand the Item,
and for an insert the id captured from `lastInsertId()` immediately after its INSERT, to a private
helper. When the repository does not own the transaction, the helper marks the Item at once. When it
does, the helper queues the pair. `saveItem()` and `saveSet()` flush the queue after `commit()` and
discard it after `rollBack()`.

The id has to be captured at insert time rather than at flush time. `lastInsertId()` is per
connection and each later insert overwrites it, so reading it after commit would give every queued
Item the id of the last insert.

*Alternative considered*: changing `insertItem()` and `updateItem()` to return the id and moving all
marking into the save methods. Rejected as a protected-signature change for no behavioural gain, when
the queue achieves the same thing internally.

*Alternative considered*: snapshotting each Item's state before the save and restoring it on rollback.
Rejected because it needs new public methods on `Item` to capture and restore state, including
un-setting an id, which widens a surface `item-state-model` deliberately keeps small. Deferring the
mark means there is nothing to undo.

### Joined and flag-less saves mark immediately

When the repository does not own the transaction, marking immediately is not a compromise, it is
required. A caller saving a parent and then its children inside its own transaction needs the
parent's generated id before inserting the children, so it can set their foreign keys. Deferring the
mark to a commit the repository never sees would make that impossible.

The cost is that a caller who rolls back its own transaction holds Items claiming to be saved. The
library cannot fix that without observing the caller's transaction, so the `$useTransaction` docblock
and the README state it: after rolling back a transaction of your own, discard or reload the Items you
saved within it.

## Risks / Trade-offs

- **Existing PDO mocks break on `inTransaction()`** → The four transaction tests in
  `tests/RepositoryBase.php` build partial mocks with `disableOriginalConstructor()` and an
  `onlyMethods()` list that omits `inTransaction`, so the guard would call the real method on an
  uninitialised PDO. The task list updates those mocks first, before any behaviour changes, so the
  suite goes green on the unchanged code and then tracks the change.

- **A repository-level queue is mutable state** → It exists only between the start and end of one
  save and is always emptied by flush or discard, including when the save throws. Tasks include a test
  that a failed save leaves nothing queued for the next one.

- **Deferred marking changes when a new Item gets its id in the owned case** → Before commit the Item
  now has no id. Nothing inside a single repository's save needs it, since every write in one save is
  to the same table. Anything that does need an id mid-transaction is by definition spanning
  repositories, which means the caller owns the transaction and marking is immediate.

- **Callers rolling back their own transactions hold stale Items** → Documented, not solved, for the
  reason above. No current consumer manages a transaction, so nobody is affected today.

## Migration Plan

1. Update the four existing PDO mocks to stub `inTransaction()`, returning false, and confirm the suite
   passes on unchanged code.
2. Add characterisation tests recording both defects as they stand: the exception when
   `$useTransaction` is true inside an active transaction, and Items reporting themselves current after
   a rolled-back `saveSet()`.
3. Introduce the ownership rule in both save methods, inverting the first characterisation test.
4. Introduce the deferred mark, inverting the second.
5. Update the docblocks and the README.

Each step is committed separately per the checkpoint discipline in `docs/standards/workflow-notes.md`.
No data migration, no dependency change; rollback is a plain revert.
