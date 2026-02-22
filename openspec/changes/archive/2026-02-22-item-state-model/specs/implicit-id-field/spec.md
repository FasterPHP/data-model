## MODIFIED Requirements

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

## REMOVED Requirements

### Requirement: assignId() for internal use
**Reason**: Replaced by `markItemPersisted(mixed $id = null)` in the item-state-model capability. The new method merges `assignId()` and `clearOriginalValues()` into a single operation with clearer intent and simpler guard logic.
**Migration**: Replace `$item->clearOriginalValues(); $item->assignId($id);` with `$item->markItemPersisted($id)`.

### Requirement: Persisted flag on Item
**Reason**: The `$persisted` flag is subsumed by the `$itemState` property. The TEMP state replaces the need for a separate persisted flag — `markItemPersisted()` transitions from TEMP to CURRENT, which is the equivalent gate.
**Migration**: No external migration needed; the flag was private and had no public API.

### Requirement: Repository insertItem call order
**Reason**: The two-step `clearOriginalValues()` → `assignId()` sequence and its ordering constraint are eliminated. `markItemPersisted($id)` is a single atomic call.
**Migration**: Replace the two-step call sequence with `$item->markItemPersisted($id)`.

### Requirement: isTemp and isDirty unaffected
**Reason**: `isTemp()` and `isDirty()` ARE affected by this change — they are now derived from `$itemState` rather than inspecting id values and `originalValues` respectively. Their new behaviour is specified in the item-state-model capability.
**Migration**: No external migration needed; the public API (`isTemp()`, `isDirty()`) is unchanged, only the internal implementation changes.
