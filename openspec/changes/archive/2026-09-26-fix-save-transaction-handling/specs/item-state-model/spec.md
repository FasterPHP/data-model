## MODIFIED Requirements

### Requirement: Repository persistence uses markItemPersisted
The Repository SHALL mark each inserted or updated Item persisted by calling `markItemPersisted()`. For an insert, if the item has no id, it SHALL pass the value from `PDO::lastInsertId()` as the `$id` argument, captured immediately after that item's INSERT executes. If the item already has an id, it SHALL call `markItemPersisted()` without arguments. For an update, it SHALL call `markItemPersisted()` without arguments.

When the Repository does not own a transaction, it SHALL call `markItemPersisted()` immediately after the item's statement executes. When the Repository owns the transaction, it SHALL defer every such call until the transaction has committed, and SHALL make none of them if the transaction rolls back.

#### Scenario: Insert with auto-increment id
- **WHEN** a temp Item (with null id) is saved via `Repository::saveItem()`
- **THEN** the Repository SHALL execute the INSERT and call `markItemPersisted($lastInsertId)`

#### Scenario: Insert with user-provided id
- **WHEN** a temp Item with an already-set id is saved via `Repository::saveItem()`
- **THEN** the Repository SHALL execute the INSERT and call `markItemPersisted()` without an id argument

#### Scenario: Update calls markItemPersisted
- **WHEN** a modified Item is saved via `Repository::saveItem()`
- **THEN** the Repository SHALL execute the UPDATE and call `markItemPersisted()` without arguments

#### Scenario: Owned transaction defers the call until commit
- **WHEN** a temp Item is saved via `Repository::saveItem()` with `$useTransaction` true and no transaction already active
- **THEN** `markItemPersisted($lastInsertId)` SHALL be called only after the transaction commits
- **AND** the id passed SHALL be the value `lastInsertId()` returned immediately after that item's INSERT

#### Scenario: Owned transaction that rolls back makes no call
- **WHEN** a Set is saved with `$useTransaction` true, no transaction already active, and a statement fails
- **THEN** `markItemPersisted()` SHALL NOT have been called on any Item in the Set
