# aggregate-field-qualification Specification

## Purpose

Defines which column identifiers `Repository` table-qualifies when it builds WHERE and HAVING
clauses, so that base-table fields, aggregate and external fields, and dot-qualified keys are each
referenced in the form the surrounding SQL accepts.

## Requirements

### Requirement: Table-qualification based on field ownership

`Repository::getComparison()` SHALL only table-qualify column identifiers that belong to the base table: the Item's ID column (named by `ID_FIELD`) and the fields in `FIELDS` or `FIELDS_READONLY`. Fields not in these sets (including `FIELDS_AGGREGATE` and `FIELDS_EXTERNAL`) SHALL be used as bare backtick-quoted identifiers. Dot-qualified keys (e.g. `table.field`) SHALL be used as-is.

A key equal to the Item's reserved `ID_INTERNAL` (`id`) SHALL refer to the ID column: it SHALL be rendered as the ID column qualified with the table name, not as the bare `id` select alias, because a select alias cannot be referenced in a WHERE clause. This applies only when the Item names an ID column.

Qualifying an identifier SHALL NOT change the placeholder it binds: the placeholder SHALL continue to be derived from the key as given.

Every key SHALL additionally be a valid identifier shape, whether or not it is recognised as a field of the base table. A key that is not a valid identifier shape SHALL cause an `Exception` to be thrown rather than being interpolated into the generated SQL. Recognition as a base-table field determines only whether the identifier is table-qualified; it does not exempt a key from validation, and an unrecognised key is not a channel for arbitrary SQL.

#### Scenario: Regular field is table-qualified
- **WHEN** `getComparison()` is called with a key that exists in `FIELDS` (e.g. `userId`)
- **THEN** the SQL fragment SHALL use the table-qualified form (e.g. `` `orders`.`userId` ``)

#### Scenario: Readonly field is table-qualified
- **WHEN** `getComparison()` is called with a key that exists in `FIELDS_READONLY`
- **THEN** the SQL fragment SHALL use the table-qualified form

#### Scenario: ID column is table-qualified
- **WHEN** `getComparison()` is called with a key equal to the Item's `ID_FIELD` (e.g. `courseId` for a repository on `courses`)
- **THEN** the SQL fragment SHALL use the table-qualified form (e.g. `` `courses`.`courseId` ``)
- **AND** the placeholder SHALL be the one derived from the bare key (e.g. `:courseId`)

#### Scenario: ID filter on a joined repository is unambiguous
- **WHEN** a repository whose from clause joins another table with a column of the same name as its ID column retrieves a Set filtered on the ID column's bare name
- **THEN** the executed WHERE condition SHALL reference the ID column qualified with the repository's table name

#### Scenario: Reserved id key resolves to the qualified ID column
- **WHEN** `getComparison()` is called with the key `id` for an Item whose `ID_FIELD` is `userId`, on a repository whose table is `users`
- **THEN** the SQL fragment SHALL use `` `users`.`userId` ``, not `` `id` ``
- **AND** the placeholder SHALL be the one derived from the key `id`

#### Scenario: Reserved id key with a list resolves to the qualified ID column
- **WHEN** a Set is retrieved with the filter `id => [1, 2, 3]` for an Item whose `ID_FIELD` is `userId`
- **THEN** the executed SQL SHALL constrain `` `users`.`userId` `` to those values with an IN list

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
- **WHEN** `getComparison()` is called with a key that is a valid identifier shape but is neither the ID column, the reserved `id`, nor in any of the field arrays
- **THEN** the SQL fragment SHALL use the bare backtick-quoted form without a table prefix

#### Scenario: Key that is not a valid identifier shape is rejected
- **WHEN** `getComparison()` is called with a key that is not a valid identifier shape, such as one containing spaces, parentheses, commas or quotation marks
- **THEN** an `Exception` SHALL be thrown whose message includes the rejected key
- **AND** no SQL fragment SHALL be produced for that key

### Requirement: HAVING clause uses bare aggregate identifiers

When filtering by aggregate fields via `getHavingSqlAndParams()`, the resulting HAVING SQL SHALL contain bare (non-table-qualified) identifiers for aggregate fields.

#### Scenario: Filtering by aggregate field produces valid HAVING SQL
- **WHEN** `getHavingSqlAndParams()` is called with an aggregate field param (e.g. `totalAmount => 1000`)
- **THEN** the HAVING SQL SHALL contain `` `totalAmount` = :totalAmount `` (bare, not `` `orders`.`totalAmount` ``)

#### Scenario: Mixed params split correctly with proper qualification
- **WHEN** params contain both regular fields (`userId`) and aggregate fields (`totalAmount`)
- **THEN** `getWhereSqlAndParams()` SHALL produce table-qualified SQL for `userId`
- **AND** `getHavingSqlAndParams()` SHALL produce bare SQL for `totalAmount`