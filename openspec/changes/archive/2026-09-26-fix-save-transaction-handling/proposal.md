## Why

The library's persistence model saves each table independently, which is sound: every write is
generated from one Item's changed values against one table. Where that model meets its limit is
atomicity across tables. Saving a parent and its children, or a chain of dependent rows, has to
succeed or fail as one, and the only way to achieve that is a transaction spanning several
repository calls. Two defects in `saveItem()` and `saveSet()` make that unsafe today.

**The transaction flag does not compose.** When `$useTransaction` is true, both methods call
`beginTransaction()` unconditionally; nothing in `Repository.php` calls `inTransaction()`. A caller
who opens a transaction to span two repositories and also passes `true` gets a PDOException
("There is already an active transaction"). On failure the inner call would also roll back the
caller's transaction, not just its own work. A caller who instead passes `true` to each save gets
independent transactions, which is not atomic, and nothing warns them.

**A rolled-back save leaves Items claiming to be saved.** `insertItem()` and `updateItem()` call
`markItemPersisted()` straight after each statement, inside the loop. If `saveSet()` fails on the
third of five Items, the rollback removes the first two rows from the database, but those Items still
report themselves as current, the inserted ones carrying ids that no longer exist. A retry of the
same Set then skips them as already saved, so their rows are silently lost.

Neither defect affects current consumers: nothing in either known consumer passes `true`
or manages a transaction. But both consumers perform multi-table writes that ought to be atomic and
currently are not. One saves a user, a password reset and a login attempt in
sequence, and another cascades results up a three-level hierarchy of
records. Fixing these defects is what makes wrapping those sequences safe.

## What Changes

- A repository **owns** a transaction only if it began it: `$useTransaction` is true and no
  transaction was already active on the connection. Only an owned transaction is committed or rolled
  back by the repository.
- When `$useTransaction` is true and a transaction is already active, the save **joins** it: no
  `beginTransaction()`, no `commit()`, and on failure no `rollBack()`. The exception propagates and
  the transaction's owner decides.
- When the repository owns the transaction, Items are marked persisted only **after commit**. If the
  transaction rolls back, every Item in the save is left in its pre-save state, temp or modified, so
  a retry saves it again.
- When the repository does not own a transaction, whether because `$useTransaction` is false or
  because it joined a caller's, Items are marked persisted immediately after each statement, as now.
  In the caller-owned case this is required: a caller saving a parent and then its children needs the
  parent's generated id in between. The consequence, that a caller rolling back its own transaction
  must discard or reload the Items it saved, is documented rather than solved, because the library
  cannot observe a transaction it does not own.

No public signature changes. `$useTransaction` keeps its name, type and default.

## Capabilities

### New Capabilities

- `repository-transactions`: when a repository owns a transaction, how a save composes with a
  transaction the caller already holds, and what state Items are left in after a commit or a rollback.

### Modified Capabilities

- `item-state-model`: the requirement "Repository persistence uses markItemPersisted" currently says
  `insertItem()` and `updateItem()` SHALL call `markItemPersisted()` straight after executing their
  statement. That becomes conditional: immediately when the repository does not own a transaction,
  and after commit when it does.

## Impact

**Affected code**

- `src/Repository.php`: `saveItem()`, `saveSet()`, `insertItem()`, `updateItem()`, plus a small
  private mechanism for deferring the persisted mark until commit.
- `tests/RepositoryBase.php`: the four existing transaction tests build partial PDO mocks whose
  `onlyMethods()` list omits `inTransaction`, so they must stub it once the guard exists.

**Affected consumers**: none change behaviour. The fix makes it safe for consumers to wrap their
multi-table write sequences in a transaction later; doing so is not part of this change.

**Documentation**: the `$useTransaction` docblocks and the README's persistence section should state
the ownership rule, the joining behaviour, and the caller's responsibility after rolling back its own
transaction.

**No dependency changes.**
