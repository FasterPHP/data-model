## MODIFIED Requirements

### Requirement: Table-qualification based on field ownership

`Repository::getComparison()` SHALL only table-qualify column identifiers that belong to the base table (`FIELDS` or `FIELDS_READONLY`). Fields not in these sets (including `FIELDS_AGGREGATE` and `FIELDS_EXTERNAL`) SHALL be used as bare backtick-quoted identifiers. Dot-qualified keys (e.g. `table.field`) SHALL be used as-is.

Every key SHALL additionally be a valid identifier shape, whether or not it is recognised as a field of the base table. A key that is not a valid identifier shape SHALL cause an `Exception` to be thrown rather than being interpolated into the generated SQL. Recognition as a base-table field determines only whether the identifier is table-qualified; it does not exempt a key from validation, and an unrecognised key is not a channel for arbitrary SQL.

#### Scenario: Regular field is table-qualified
- **WHEN** `getComparison()` is called with a key that exists in `FIELDS` (e.g. `userId`)
- **THEN** the SQL fragment SHALL use the table-qualified form (e.g. `` `orders`.`userId` ``)

#### Scenario: Readonly field is table-qualified
- **WHEN** `getComparison()` is called with a key that exists in `FIELDS_READONLY`
- **THEN** the SQL fragment SHALL use the table-qualified form

#### Scenario: Aggregate field is NOT table-qualified
- **WHEN** `getComparison()` is called with a key that exists in `FIELDS_AGGREGATE` (e.g. `totalAmount`)
- **THEN** the SQL fragment SHALL use the bare form (e.g. `` `totalAmount` ``) without a table prefix

#### Scenario: External field is NOT table-qualified
- **WHEN** `getComparison()` is called with a key that exists in `FIELDS_EXTERNAL`
- **THEN** the SQL fragment SHALL use the bare form without a table prefix

#### Scenario: Dot-qualified key is used as-is
- **WHEN** `getComparison()` is called with a key containing a dot (e.g. `a.createdDate`)
- **THEN** the SQL fragment SHALL use the key as-is with backtick-escaping (e.g. `` `a`.`createdDate` ``)

#### Scenario: Unrecognised key of valid identifier shape is accepted bare
- **WHEN** `getComparison()` is called with a key that is a valid identifier shape but appears in none of the field arrays
- **THEN** the SQL fragment SHALL use the bare backtick-quoted form without a table prefix

#### Scenario: Key that is not a valid identifier shape is rejected
- **WHEN** `getComparison()` is called with a key that is not a valid identifier shape, such as one containing spaces, parentheses, commas or quotation marks
- **THEN** an `Exception` SHALL be thrown whose message includes the rejected key
- **AND** no SQL fragment SHALL be produced for that key
