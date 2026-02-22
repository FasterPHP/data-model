## MODIFIED Requirements

### Requirement: assignId() for internal use
The Item class SHALL provide a public `assignId(mixed $id): void` method that sets the id field value. This method SHALL only succeed when both conditions are met: the item has been through a persistence operation (`clearOriginalValues()` has been called) AND the item is temporary (has no id). The method MUST NOT be declared on `ItemInterface`.

#### Scenario: assignId succeeds after clearOriginalValues on a temp item
- **WHEN** a new Item has `clearOriginalValues()` called, then `assignId(99)` is called
- **THEN** `getId()` SHALL return `99` and `isTemp()` SHALL return `false`

#### Scenario: assignId throws on a temp item before persistence
- **WHEN** `assignId(99)` is called on a newly constructed Item without `clearOriginalValues()` having been called
- **THEN** an `Exception` SHALL be thrown with a message containing "managed automatically"

#### Scenario: assignId throws on a loaded item
- **WHEN** `assignId(2)` is called on an Item loaded from the database with id `1`
- **THEN** an `Exception` SHALL be thrown with a message containing "managed automatically"

#### Scenario: assignId throws on a loaded item even after clearOriginalValues
- **WHEN** an Item with id `1` has `clearOriginalValues()` called, then `assignId(2)` is called
- **THEN** an `Exception` SHALL be thrown with a message containing "managed automatically" (because `isTemp()` is false)

#### Scenario: assignId is not on ItemInterface
- **WHEN** the `ItemInterface` is inspected
- **THEN** it SHALL NOT declare an `assignId` method

## ADDED Requirements

### Requirement: Persisted flag on Item
The Item class SHALL maintain a private `$persisted` flag, initially `false`, that is set to `true` when `clearOriginalValues()` is called. This flag is not directly accessible or inspectable from outside the class.

#### Scenario: Persisted flag is false on new item
- **WHEN** a new Item is constructed
- **THEN** the internal persisted flag SHALL be `false`

#### Scenario: clearOriginalValues sets persisted flag
- **WHEN** `clearOriginalValues()` is called on an Item
- **THEN** the internal persisted flag SHALL be `true`

### Requirement: Repository insertItem call order
The `Repository::insertItem()` method SHALL call `clearOriginalValues()` before `assignId()`, so that the persisted flag is set before the id assignment is attempted.

#### Scenario: Insert flow order
- **WHEN** a new Item (with null id) is saved via `Repository::saveItem()`
- **THEN** the Repository SHALL execute the INSERT, call `clearOriginalValues()`, then call `assignId()` with the value from `PDO::lastInsertId()`
