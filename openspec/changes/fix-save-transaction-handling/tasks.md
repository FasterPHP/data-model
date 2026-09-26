## 1. Test harness

- [x] 1.1 Add `inTransaction` to the `onlyMethods()` list of the partial PDO mocks in `testSaveSetWithTransaction()`, `testSaveSetTransactionRollback()`, `testSaveItemWithTransaction()` and `testSaveItemTransactionRollback()` in `tests/RepositoryBase.php`, stubbed to return false, so they do not call PDO's real method on an uninitialised mock once the guard exists; verify on the unchanged code with `vendor/bin/phpunit --no-coverage`, which must still report 419 tests and 1278 assertions

## 2. Baseline characterisation

- [x] 2.1 Add a test using an in-memory SQLite PDO, as `tests/AggregateFieldsRepositoryTest.php` does, in which the caller begins a transaction and then calls `saveItem()` with `$useTransaction` true, recording that it currently throws because a transaction is already active; verify with `vendor/bin/phpunit --no-coverage --filter Transaction`
- [x] 2.2 Add a test using in-memory SQLite in which `saveSet()` with `$useTransaction` true fails on the third of several new Items (for example through a constraint violation), recording that the first two Items currently report themselves non-temporary with ids although the rollback removed their rows; verify with `vendor/bin/phpunit --no-coverage --filter Transaction`

## 3. Transaction ownership

- [x] 3.1 In `saveItem()` and `saveSet()`, compute whether the repository owns the transaction once, before any work, as `$useTransaction && !$this->pdo->inTransaction()`, and begin, commit and roll back only when it does; verify the test from 2.1 now passes with the inverted expectation, and the three "A repository owns only the transactions it begins" scenarios pass
- [x] 3.2 Add tests for failure inside a caller's transaction, asserting no `rollBack()` is called, the original exception is rethrown, and `inTransaction()` is still true afterwards; verify the "Failure inside a caller's transaction" scenario passes
- [x] 3.3 Add an in-memory SQLite test in which the caller begins a transaction, saves Items through two different repositories with `$useTransaction` true, and either commits or rolls back, asserting by querying the tables that the writes are durable together or not at all; verify the "Several repositories share one caller transaction" scenario passes

## 4. Deferred persisted mark

- [x] 4.1 Add a private helper that `insertItem()` and `updateItem()` call instead of `markItemPersisted()` directly, passing the Item and, for an insert, the id read from `lastInsertId()` immediately after that INSERT; the helper marks at once when the repository does not own the transaction and queues the pair when it does; verify the full suite still passes with `vendor/bin/phpunit --no-coverage`
- [x] 4.2 Flush the queue after `commit()` and discard it after `rollBack()` in both save methods, ensuring it is emptied on every exit path; verify the test from 2.2 now passes with the inverted expectation, so the first two Items remain temporary with no id
- [x] 4.3 Add tests for the "Items reflect the outcome of an owned transaction" requirement: Items marked only after commit, rolled-back Items keeping their pre-save state, a retried Set writing every Item, and several inserts in one transaction each receiving their own generated id; verify with `vendor/bin/phpunit --no-coverage --filter Transaction`
- [x] 4.4 Add tests for the "Items saved outside an owned transaction are marked immediately" requirement, including a caller saving a new parent inside its own transaction and reading the parent's generated id before committing; verify both scenarios pass
- [x] 4.5 Add a test in which one owned-transaction save fails and a subsequent save on the same repository succeeds, asserting no Item from the failed save is marked by the later flush; verify with `vendor/bin/phpunit --no-coverage --filter Transaction`
- [x] 4.6 Add tests for the two new `item-state-model` scenarios, asserting through a mock that `markItemPersisted()` is called only after `commit()` with the id captured at insert time, and not at all when the save rolls back; verify with `vendor/bin/phpunit --no-coverage --filter Repository`

## 5. Documentation

- [x] 5.1 Update the `$useTransaction` docblocks on `saveItem()` and `saveSet()` to state that the repository begins a transaction only when none is active, joins an active one otherwise, and that a caller rolling back a transaction of its own must discard or reload the Items saved within it; verify by reading both docblocks against the `repository-transactions` spec
- [x] 5.2 Update the persistence section of `README.md` with the same rule and a short example of a caller-owned transaction spanning two repositories, including reading a parent's generated id before saving its children; verify by reading the section against the spec and running any example it references

## 6. Final verification

- [ ] 6.1 Run `vendor/bin/phpcs` and confirm zero violations
- [ ] 6.2 Run `vendor/bin/phpunit --no-coverage` and confirm the full suite passes with more than the 419 tests and 1278 assertions recorded when this change was planned, every characterisation test from group 2 now asserting the corrected behaviour
