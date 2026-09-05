## Purpose

Defines which column identifiers `Repository` table-qualifies when it builds WHERE and HAVING
clauses, so that base-table fields, aggregate and external fields, and dot-qualified keys are each
referenced in the form the surrounding SQL accepts.

## Requirements

### Requirement: Table-qualification based on field ownership

`Repository::getComparison()` SHALL only table-qualify column identifiers that belong to the base table (`FIELDS` or `FIELDS_READONLY`). Fields not in these sets (including `FIELDS_AGGREGATE` and `FIELDS_EXTERNAL`) SHALL be used as bare backtick-quoted identifiers. Dot-qualified keys (e.g. `table.field`) SHALL be used as-is.

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

### Requirement: HAVING clause uses bare aggregate identifiers

When filtering by aggregate fields via `getHavingSqlAndParams()`, the resulting HAVING SQL SHALL contain bare (non-table-qualified) identifiers for aggregate fields.

#### Scenario: Filtering by aggregate field produces valid HAVING SQL
- **WHEN** `getHavingSqlAndParams()` is called with an aggregate field param (e.g. `totalAmount => 1000`)
- **THEN** the HAVING SQL SHALL contain `` `totalAmount` = :totalAmount `` (bare, not `` `orders`.`totalAmount` ``)

#### Scenario: Mixed params split correctly with proper qualification
- **WHEN** params contain both regular fields (`userId`) and aggregate fields (`totalAmount`)
- **THEN** `getWhereSqlAndParams()` SHALL produce table-qualified SQL for `userId`
- **AND** `getHavingSqlAndParams()` SHALL produce bare SQL for `totalAmount`
