## MODIFIED Requirements

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
