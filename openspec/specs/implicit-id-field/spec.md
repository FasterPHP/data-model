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

#### Scenario: Id field initialised from constructor data for non-temp items
- **WHEN** an Item is constructed with `['id' => 42]` in the data array and `isTemp: false`
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
The `jsonSerialize()` and `__toString()` methods SHALL include the id value in their output, prepended to the field values from `getValues()`. The `__serialize()` method SHALL include the id value within the `'values'` key of a structured array that also contains `'originalValues'` and `'toDelete'`.

#### Scenario: JSON serialisation includes id
- **WHEN** `json_encode($item)` is called on an Item with id `5` and name `'Alice'`
- **THEN** the JSON output SHALL contain `"id":5` alongside the other field values

#### Scenario: __serialize includes id within values key
- **WHEN** `serialize($item)` is called on an Item with id `5`
- **THEN** the serialised array's `'values'` key SHALL contain the `'id'` key with value `5`

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

### Requirement: Repository getFieldList includes id in SELECT
The `Repository::getFieldList()` method SHALL include the id column (aliased from `ID_FIELD` to `ID_INTERNAL`) in its SELECT output, even though the id is not in `FIELDS` or `FIELDS_READONLY`.

#### Scenario: SELECT includes aliased id column
- **WHEN** `getFieldList()` is called for an Item with `ID_FIELD = 'userId'` and fields `['name', 'email']`
- **THEN** the output SHALL contain `"userId" AS "id"` alongside `"name"` and `"email"`
