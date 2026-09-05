# item-state-model Specification

## Purpose

Defines the Item lifecycle state model - temp, current and modified - and the transitions between
those states, covering construction, value changes, persistence, serialisation and the points at
which Set and Repository hydrate or persist items.

## Requirements

### Requirement: Item state constants
The Item class SHALL define three private constants representing lifecycle states: `ITEM_STATE_TEMP` (value `'temp'`), `ITEM_STATE_CURRENT` (value `'current'`), and `ITEM_STATE_MODIFIED` (value `'modified'`). The Item class SHALL maintain a private `string $itemState` property that holds one of these values at all times.

#### Scenario: Constants are private
- **WHEN** the Item class is inspected via reflection
- **THEN** `ITEM_STATE_TEMP`, `ITEM_STATE_CURRENT`, and `ITEM_STATE_MODIFIED` SHALL all be private constants

#### Scenario: State property is private
- **WHEN** the Item class is inspected via reflection
- **THEN** the `$itemState` property SHALL be private

### Requirement: Constructor isTemp parameter
The Item constructor SHALL accept a second parameter `bool $isTemp = true`. When `$isTemp` is `true`, the initial state SHALL be TEMP. When `$isTemp` is `false`, the initial state SHALL be CURRENT. The parameter SHALL NOT be stored as a separate property.

#### Scenario: Default construction creates temp item
- **WHEN** an Item is constructed with `new FooItem()`
- **THEN** `isTemp()` SHALL return `true` and `isDirty()` SHALL return `false`

#### Scenario: Construction with isTemp false creates current item
- **WHEN** an Item is constructed with `new FooItem(['id' => 5, 'name' => 'Alice'], isTemp: false)`
- **THEN** `isTemp()` SHALL return `false` and `isDirty()` SHALL return `false`

#### Scenario: Backward compatibility preserved
- **WHEN** an Item is constructed with `new FooItem()` (no second argument)
- **THEN** the behaviour SHALL be identical to `new FooItem([], isTemp: true)`

### Requirement: Constructor guard rejects id on temp items
When `$isTemp` is `true` (the default), the constructor SHALL throw an `Exception` if the data array contains a non-empty value for the key matching `ID_INTERNAL`.

#### Scenario: Temp item with id throws
- **WHEN** `new FooItem(['id' => 5])` is called
- **THEN** an `Exception` SHALL be thrown with a message indicating a temporary item cannot have an id

#### Scenario: Temp item without id is allowed
- **WHEN** `new FooItem(['name' => 'Alice'])` is called
- **THEN** no exception SHALL be thrown and `isTemp()` SHALL return `true`

#### Scenario: Non-temp item with id is allowed
- **WHEN** `new FooItem(['id' => 5, 'name' => 'Alice'], isTemp: false)` is called
- **THEN** no exception SHALL be thrown and `getId()` SHALL return `5`

### Requirement: isTemp derived from state
The `isTemp()` method SHALL return `true` if and only if the item's state is TEMP. It SHALL NOT inspect the id value or `originalValues`.

#### Scenario: New item is temporary
- **WHEN** an Item is constructed with no data
- **THEN** `isTemp()` SHALL return `true`

#### Scenario: Loaded item is not temporary
- **WHEN** an Item is constructed with `['id' => 1]` and `isTemp: false`
- **THEN** `isTemp()` SHALL return `false`

#### Scenario: Item becomes non-temporary after markItemPersisted
- **WHEN** a temp Item has `markItemPersisted(42)` called
- **THEN** `isTemp()` SHALL return `false`

### Requirement: isDirty derived from state
The `isDirty()` method SHALL return `true` if and only if the item's state is MODIFIED.

#### Scenario: New item is not dirty
- **WHEN** an Item is constructed with no data
- **THEN** `isDirty()` SHALL return `false`

#### Scenario: Loaded item is not dirty
- **WHEN** an Item is constructed with `['id' => 1, 'name' => 'Alice']` and `isTemp: false`
- **THEN** `isDirty()` SHALL return `false`

#### Scenario: Modified item is dirty
- **WHEN** a loaded Item (CURRENT state) has a field value changed via a setter
- **THEN** `isDirty()` SHALL return `true`

#### Scenario: Temp item with set values is not dirty
- **WHEN** a temp Item has a field value set via a setter
- **THEN** `isDirty()` SHALL return `false` (state remains TEMP, not MODIFIED)

### Requirement: State transition on setValue
When `setValue()` is called on a CURRENT item and the change results in a non-empty `originalValues`, the state SHALL transition to MODIFIED. When `setValue()` is called on a MODIFIED item and the change results in an empty `originalValues` (all changes reverted), the state SHALL transition back to CURRENT. When `setValue()` is called on a TEMP item, the state SHALL remain TEMP regardless of `originalValues` content. When `setValue()` is called for a `FIELDS_READONLY` field on a TEMP item, the value SHALL be set without error. When `setValue()` is called for a `FIELDS_READONLY` field on a CURRENT or MODIFIED item, an `Exception` SHALL be thrown. When `setValue()` is called for a `FIELDS_EXTERNAL` or `FIELDS_AGGREGATE` field on any item regardless of state, an `Exception` SHALL be thrown.

#### Scenario: CURRENT to MODIFIED on field change
- **WHEN** a loaded Item (CURRENT state) has `setName('Bob')` called (changing from `'Alice'`)
- **THEN** `isDirty()` SHALL return `true`

#### Scenario: MODIFIED to CURRENT on full revert
- **WHEN** a loaded Item with name `'Alice'` has `setName('Bob')` called, then `setName('Alice')` called
- **THEN** `isDirty()` SHALL return `false`

#### Scenario: TEMP stays TEMP on field change
- **WHEN** a temp Item has `setName('Bob')` called
- **THEN** `isTemp()` SHALL return `true` and `isDirty()` SHALL return `false`

#### Scenario: Setting a readonly field on a temp item succeeds
- **WHEN** a temp Item with a `FIELDS_READONLY` field `userId` has `setUserId(42)` called
- **THEN** `getUserId()` SHALL return `42` and no exception SHALL be thrown

#### Scenario: Setting a readonly field on a CURRENT item throws
- **WHEN** a loaded Item (CURRENT state) with a `FIELDS_READONLY` field `userId` has `setUserId(99)` called
- **THEN** an `Exception` SHALL be thrown with a message containing "read-only field"

#### Scenario: Setting a readonly field on a MODIFIED item throws
- **WHEN** a MODIFIED Item with a `FIELDS_READONLY` field `userId` has `setUserId(99)` called
- **THEN** an `Exception` SHALL be thrown with a message containing "read-only field"

#### Scenario: Setting an external field on a temp item throws
- **WHEN** a temp Item with a `FIELDS_EXTERNAL` field `computedName` has `setComputedName('x')` called
- **THEN** an `Exception` SHALL be thrown with a message containing "read-only field"

#### Scenario: Setting an aggregate field on a temp item throws
- **WHEN** a temp Item with a `FIELDS_AGGREGATE` field `totalCount` has `setTotalCount(5)` called
- **THEN** an `Exception` SHALL be thrown with a message containing "read-only field"

### Requirement: markItemPersisted replaces clearOriginalValues and assignId
The Item class SHALL provide a public `markItemPersisted(mixed $id = null): void` method. When called, it SHALL clear `originalValues` and set the state to CURRENT. If the `$id` parameter is non-null, the method SHALL require the current state to be TEMP and set the id field value before transitioning. If `$id` is non-null and the state is not TEMP, an `Exception` SHALL be thrown. The method MUST NOT be declared on `ItemInterface`.

#### Scenario: markItemPersisted on temp item with id
- **WHEN** `markItemPersisted(99)` is called on a temp Item
- **THEN** `getId()` SHALL return `99`, `isTemp()` SHALL return `false`, and `isDirty()` SHALL return `false`

#### Scenario: markItemPersisted on temp item without id (user-provided id)
- **WHEN** a temp Item is constructed with a user-provided id column value (non-auto-increment), and `markItemPersisted()` is called without an id argument
- **THEN** the existing id SHALL be preserved and `isTemp()` SHALL return `false`

#### Scenario: markItemPersisted on modified item
- **WHEN** `markItemPersisted()` is called on a MODIFIED Item (after UPDATE)
- **THEN** `isDirty()` SHALL return `false` and `originalValues` SHALL be empty

#### Scenario: markItemPersisted with id throws on non-temp item
- **WHEN** `markItemPersisted(2)` is called on a CURRENT Item with id `1`
- **THEN** an `Exception` SHALL be thrown

#### Scenario: markItemPersisted is not on ItemInterface
- **WHEN** the `ItemInterface` is inspected
- **THEN** it SHALL NOT declare a `markItemPersisted` method

#### Scenario: markItemPersisted returns void
- **WHEN** `markItemPersisted()` is called
- **THEN** the return type SHALL be `void`

### Requirement: clearOriginalValues removed from ItemInterface
The `ItemInterface` SHALL NOT declare a `clearOriginalValues()` method.

#### Scenario: clearOriginalValues absent from interface
- **WHEN** the `ItemInterface` is inspected
- **THEN** it SHALL NOT declare a `clearOriginalValues` method

### Requirement: Full-state serialisation
The `__serialize()` method SHALL return an array containing three keys: `'values'` (the id prepended to `getValues()`), `'originalValues'` (the current `originalValues` array), and `'toDelete'` (the current `toDelete` flag). The `jsonSerialize()` method SHALL remain unchanged, returning the id prepended to `getValues()`.

#### Scenario: __serialize includes full state
- **WHEN** `serialize($item)` is called on a MODIFIED Item with id `5`, name changed from `'Alice'` to `'Bob'`
- **THEN** the serialised array SHALL contain `'values'` with `['id' => 5, 'name' => 'Bob']`, `'originalValues'` with a key for `'name'`, and `'toDelete'` as `false`

#### Scenario: __serialize includes toDelete flag
- **WHEN** `serialize($item)` is called on an Item marked for deletion
- **THEN** the serialised array's `'toDelete'` key SHALL be `true`

#### Scenario: jsonSerialize remains unchanged
- **WHEN** `json_encode($item)` is called on an Item with id `5` and name `'Alice'`
- **THEN** the JSON output SHALL contain `"id":5` and `"name":"Alice"` without `originalValues` or `toDelete`

### Requirement: Full-state unserialisation
The `__unserialize()` method SHALL restore `data` from the `'values'` key, `originalValues` from the `'originalValues'` key, and `toDelete` from the `'toDelete'` key. The `itemState` SHALL be derived: TEMP if the data contains no id, MODIFIED if `originalValues` is non-empty, CURRENT otherwise.

#### Scenario: Unserialise a current item
- **WHEN** an Item with id `5`, empty `originalValues`, and `toDelete: false` is serialised and then unserialised
- **THEN** `isTemp()` SHALL return `false`, `isDirty()` SHALL return `false`, and `isToDelete()` SHALL return `false`

#### Scenario: Unserialise a modified item
- **WHEN** an Item with id `5` and non-empty `originalValues` is serialised and then unserialised
- **THEN** `isDirty()` SHALL return `true`

#### Scenario: Unserialise a temp item
- **WHEN** a temp Item (no id) is serialised and then unserialised
- **THEN** `isTemp()` SHALL return `true`

#### Scenario: Unserialise an item marked for deletion
- **WHEN** an Item marked for deletion is serialised and then unserialised
- **THEN** `isToDelete()` SHALL return `true`

### Requirement: __toString delegates to jsonSerialize
The `__toString()` method SHALL return `json_encode($this->jsonSerialize())` rather than independently gathering field values.

#### Scenario: __toString output matches jsonSerialize
- **WHEN** `(string) $item` is called on an Item with id `5` and name `'Alice'`
- **THEN** the output SHALL equal `json_encode($item->jsonSerialize())`

### Requirement: Set hydrates items as non-temp
The `Set::getItem()` method SHALL pass `isTemp: false` when constructing Item instances from raw data arrays. The `Set::createItem()` method SHALL continue to create temp items (using the default `isTemp: true`).

#### Scenario: Set-hydrated item is not temp
- **WHEN** a Set containing raw data rows is accessed via array offset
- **THEN** the resulting Item SHALL have `isTemp()` return `false`

#### Scenario: Set::createItem produces temp item
- **WHEN** `createItem()` is called on a Set
- **THEN** the resulting Item SHALL have `isTemp()` return `true`

### Requirement: Repository getItemWithParams bypasses Set
The `Repository::getItemWithParams()` method SHALL fetch data via `getDataWithParams()` and create a single Item directly using `createItem()`, without creating a Set instance.

#### Scenario: Single item loaded without Set
- **WHEN** `getItemWithParams()` is called with matching parameters
- **THEN** a single Item SHALL be returned without instantiating a Set

#### Scenario: No match returns null
- **WHEN** `getItemWithParams()` is called with parameters matching no rows
- **THEN** `null` SHALL be returned

### Requirement: Repository createItem produces non-temp items
The `Repository::createItem()` factory method SHALL construct Item instances with `isTemp: false`, since it is only used with database row data.

#### Scenario: createItem produces non-temp item
- **WHEN** `createItem(['id' => 1, 'name' => 'Alice'])` is called on a Repository
- **THEN** the resulting Item SHALL have `isTemp()` return `false`

### Requirement: Repository persistence uses markItemPersisted
The `Repository::insertItem()` method SHALL call `markItemPersisted()` after executing the INSERT statement. If the item has no id, it SHALL pass the value from `PDO::lastInsertId()` as the `$id` argument. If the item already has an id, it SHALL call `markItemPersisted()` without arguments. The `Repository::updateItem()` method SHALL call `markItemPersisted()` without arguments after executing the UPDATE statement.

#### Scenario: Insert with auto-increment id
- **WHEN** a temp Item (with null id) is saved via `Repository::saveItem()`
- **THEN** the Repository SHALL execute the INSERT and call `markItemPersisted($lastInsertId)`

#### Scenario: Insert with user-provided id
- **WHEN** a temp Item with an already-set id is saved via `Repository::saveItem()`
- **THEN** the Repository SHALL execute the INSERT and call `markItemPersisted()` without an id argument

#### Scenario: Update calls markItemPersisted
- **WHEN** a modified Item is saved via `Repository::saveItem()`
- **THEN** the Repository SHALL execute the UPDATE and call `markItemPersisted()` without arguments