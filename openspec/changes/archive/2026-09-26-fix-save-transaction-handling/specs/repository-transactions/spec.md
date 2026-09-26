## Purpose

Defines when a repository save runs inside a transaction of its own, how it behaves inside a
transaction the caller already holds, and what state the saved Items are left in after a commit or a
rollback, so that multi-table writes can be made atomic and a failed save can be retried safely.

## ADDED Requirements

### Requirement: A repository owns only the transactions it begins

A save called with `$useTransaction` true SHALL begin a transaction only if none is active on the
connection. A repository SHALL commit or roll back only a transaction it began. A save called with
`$useTransaction` false SHALL neither begin, commit nor roll back a transaction.

#### Scenario: No active transaction and flag set
- **WHEN** `saveItem()` or `saveSet()` is called with `$useTransaction` true and no transaction is active
- **THEN** the repository SHALL begin a transaction before the first statement
- **AND** SHALL commit it after the last statement succeeds

#### Scenario: Owned transaction rolls back on failure
- **WHEN** a statement fails during a save whose transaction the repository began
- **THEN** the repository SHALL roll back that transaction
- **AND** SHALL rethrow the original exception

#### Scenario: Flag not set
- **WHEN** `saveItem()` or `saveSet()` is called with `$useTransaction` false
- **THEN** the repository SHALL NOT begin, commit or roll back any transaction

### Requirement: A save joins a transaction the caller already holds

When `$useTransaction` is true and a transaction is already active on the connection, the save SHALL
run within that transaction without beginning, committing or rolling back. A failure SHALL propagate
without altering the transaction, leaving the decision to its owner.

#### Scenario: Flag set inside a caller's transaction
- **WHEN** the caller has begun a transaction and calls `saveItem()` or `saveSet()` with `$useTransaction` true
- **THEN** no exception SHALL be raised on account of the active transaction
- **AND** the repository SHALL NOT call `beginTransaction()` or `commit()`

#### Scenario: Failure inside a caller's transaction
- **WHEN** a statement fails during a save that joined a caller's transaction
- **THEN** the repository SHALL NOT roll back the transaction
- **AND** SHALL rethrow the original exception
- **AND** the caller's transaction SHALL still be active

#### Scenario: Several repositories share one caller transaction
- **WHEN** the caller begins a transaction, saves Items through two different repositories on the same
  connection with `$useTransaction` true, and then commits
- **THEN** all the writes SHALL become durable together
- **AND** if the caller rolls back instead, none of them SHALL be durable

### Requirement: Items reflect the outcome of an owned transaction

When the repository owns the transaction, each Item in the save SHALL be marked persisted only after
the transaction commits. If the transaction rolls back, every Item in the save SHALL be left in the
state it had before the save began, so that saving the same Item or Set again repeats every write.

#### Scenario: Items are marked only after commit
- **WHEN** a Set containing new and modified Items is saved in an owned transaction that commits
- **THEN** every new Item SHALL afterwards be non-temporary and carry its generated id
- **AND** every modified Item SHALL afterwards be non-dirty

#### Scenario: Rolled-back Items keep their pre-save state
- **WHEN** a Set is saved in an owned transaction and a statement fails after earlier Items in the Set
  were written
- **THEN** the Items written before the failure SHALL still be temporary or dirty, as they were before
  the save
- **AND** a new Item SHALL NOT carry an id assigned during the failed save

#### Scenario: Retrying a rolled-back Set repeats every write
- **WHEN** a Set whose owned-transaction save rolled back is saved again and succeeds
- **THEN** every Item that was new or modified before the first attempt SHALL be written by the second

#### Scenario: Generated ids are captured as each insert happens
- **WHEN** several new Items are inserted in one owned transaction that commits
- **THEN** each Item SHALL receive the id generated for its own insert, not the id of a later insert

### Requirement: Items saved outside an owned transaction are marked immediately

When the repository does not own a transaction, each Item SHALL be marked persisted as soon as its
statement succeeds. This applies both to saves with `$useTransaction` false and to saves that joined a
caller's transaction. A caller that rolls back a transaction of its own SHALL be responsible for
discarding or reloading the Items saved within it.

#### Scenario: Generated id available to the caller mid-transaction
- **WHEN** inside its own transaction, the caller saves a new parent Item and then reads its id
- **THEN** the id SHALL be the one generated for the parent's insert
- **AND** SHALL be available before the caller commits, so dependent rows can reference it

#### Scenario: Save without a transaction
- **WHEN** a new Item is saved with `$useTransaction` false and the insert succeeds
- **THEN** the Item SHALL be non-temporary and carry its generated id when the save returns
