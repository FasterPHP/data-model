## ADDED Requirements

### Requirement: Implicit id field creation
The Item base class SHALL automatically create and manage the id field using the `ID_FIELD`, `ID_INTERNAL`, and `ID_TYPE` constants. Item subclasses MUST NOT declare the id field in `FIELDS`, `FIELDS_READONLY`, `FIELDS_EXTERNAL`, or `FIELDS_AGGREGATE`.

#### Scenario: Id field is accessible without being declared in FIELDS
- **WHEN** an Item subclass defines `ID_FIELD` but does not include `'id'` in `FIELDS`
- **THEN** calling `getId()` on an instance SHALL return the id value (or null for new items)

#### Scenario: Id field type is determined by ID_TYPE
- **WHEN** an Item subclass does not override `ID_TYPE`
- **THEN** the id field SHALL be created as `Field\Integer`

#### Scenario: Id field type is customisable
- **WHEN** an Item subclass overrides `ID_TYPE` (e.g. `public const ID_TYPE = Field\Varchar::class`)
- **THEN** the id field SHALL be created using that field type

#### Scenario: Id field initialised from constructor data
- **WHEN** an Item is constructed with `['id' => 42]` in the data array
- **THEN** `getId()` SHALL return the value cast by the `ID_TYPE` field (e.g. `42` as int)

### Requirement: Id field excluded from user-facing field lists
The id field SHALL NOT appear in the output of `getValues()` or `getSqlValues()`, since these methods enumerate `FIELDS` and `FIELDS_READONLY` and the id is in neither.

#### Scenario: getValues() excludes id
- **WHEN** `getValues()` is called on an Item with id `5`, name `'Alice'`
- **THEN** the returned array SHALL contain `'name'` but SHALL NOT contain an `'id'` key

#### Scenario: getSqlValues() excludes id
- **WHEN** `getSqlValues()` is called on an Item
- **THEN** the returned array SHALL NOT contain an `'id'` key

### Requirement: Serialisation includes id
The `jsonSerialize()`, `__serialize()`, and `__toString()` methods SHALL include the id value in their output, prepended to the field values from `getValues()`.

#### Scenario: JSON serialisation includes id
- **WHEN** `json_encode($item)` is called on an Item with id `5` and name `'Alice'`
- **THEN** the JSON output SHALL contain `"id":5` alongside the other field values

#### Scenario: __serialize includes id
- **WHEN** `serialize($item)` is called on an Item with id `5`
- **THEN** the serialised array SHALL contain the `'id'` key with value `5`

#### Scenario: __toString includes id
- **WHEN** an Item with id `5` is cast to string
- **THEN** the string SHALL contain the id value

### Requirement: setId() is blocked via __call
Calling `setId()` on an Item (via the magic `__call` dispatcher) SHALL throw an `Exception` with a message indicating the id field is managed automatically.

#### Scenario: setId() throws on a new item
- **WHEN** `setId(123)` is called on a newly constructed Item
- **THEN** an `Exception` SHALL be thrown with a message containing "managed automatically"

#### Scenario: setId() throws on a loaded item
- **WHEN** `setId(456)` is called on an Item loaded from the database with id `1`
- **THEN** an `Exception` SHALL be thrown with a message containing "managed automatically"

### Requirement: assignId() for internal use
The Item class SHALL provide a public `assignId(mixed $id): void` method that sets the id field value, bypassing the `__call` guard. This method is intended for Repository use after INSERT.

#### Scenario: assignId sets the id on a new item
- **WHEN** `assignId(99)` is called on a newly constructed Item
- **THEN** `getId()` SHALL return `99` and `isTemp()` SHALL return `false`

#### Scenario: assignId overwrites a previous id
- **WHEN** an Item has id `1` and `assignId(2)` is called
- **THEN** `getId()` SHALL return `2`

### Requirement: ID_TYPE constant with default
The Item base class SHALL define `public const ID_TYPE = Field\Integer::class` as the default id field type. Subclasses MAY override this constant to use a different field type.

#### Scenario: Default ID_TYPE is Field\Integer
- **WHEN** an Item subclass does not override `ID_TYPE`
- **THEN** `ID_TYPE` SHALL equal `Field\Integer::class`

### Requirement: Error when id declared in FIELDS
If an Item subclass declares a key matching `ID_INTERNAL` in `FIELDS`, the system SHALL throw an `Exception` with a message guiding the developer to remove it and optionally set `ID_TYPE`.

#### Scenario: Exception on id in FIELDS
- **WHEN** `getField('id')` is called on an Item subclass that has `'id' => Field\Integer::class` in `FIELDS`
- **THEN** an `Exception` SHALL be thrown with a message containing "Do not declare" and "ID_TYPE"

### Requirement: Repository uses assignId after INSERT
The `Repository::insertItem()` method SHALL call `$item->assignId()` instead of `$item->setId()` to write the auto-increment value after a successful INSERT.

#### Scenario: Auto-increment id assigned after insert
- **WHEN** a new Item (with null id) is saved via `Repository::saveItem()`
- **THEN** the Repository SHALL call `assignId()` with the value from `PDO::lastInsertId()` and the Item's `getId()` SHALL return that value

#### Scenario: Id not overwritten when already set
- **WHEN** a new Item already has an id assigned (via `assignId()`) before save
- **THEN** the Repository SHALL NOT call `assignId()` again (preserving the existing id)

### Requirement: Repository getFieldList includes id in SELECT
The `Repository::getFieldList()` method SHALL include the id column (aliased from `ID_FIELD` to `ID_INTERNAL`) in its SELECT output, even though the id is not in `FIELDS` or `FIELDS_READONLY`.

#### Scenario: SELECT includes aliased id column
- **WHEN** `getFieldList()` is called for an Item with `ID_FIELD = 'userId'` and fields `['name', 'email']`
- **THEN** the output SHALL contain `"userId" AS "id"` alongside `"name"` and `"email"`

### Requirement: isTemp and isDirty unaffected
The `isTemp()` and `isDirty()` methods SHALL continue to function as before, using the id value from the internal data array.

#### Scenario: New item is temporary
- **WHEN** an Item is constructed with no data
- **THEN** `isTemp()` SHALL return `true`

#### Scenario: Item with assigned id is not temporary
- **WHEN** `assignId(1)` is called on a new Item
- **THEN** `isTemp()` SHALL return `false`

#### Scenario: Dirty tracking unaffected by id changes via assignId
- **WHEN** `assignId(1)` is called on a new Item with no other changes
- **THEN** `isDirty()` SHALL return `false` (assignId does not track changes in originalValues)
