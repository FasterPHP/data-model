# field-uniqueness-validation Specification

## Purpose

Defines the rule that every field name resolves through exactly one of an Item's field arrays,
including the implicit id field, so that an ambiguous declaration is reported as an error rather
than silently resolved to whichever array is consulted first.

## Requirements

### Requirement: Field overlap detection
When `getField()` resolves a field, it SHALL verify the field is defined in at most one of FIELDS, FIELDS_READONLY, FIELDS_EXTERNAL, or FIELDS_AGGREGATE. If the field appears in more than one array, it SHALL throw an `Exception` whose message includes the field name.

#### Scenario: Field defined in two arrays
- **WHEN** a field name exists in both FIELDS and FIELDS_READONLY
- **THEN** `getField()` throws an `Exception` with a message indicating the field is defined in multiple field arrays

#### Scenario: Field defined in three or more arrays
- **WHEN** a field name exists in FIELDS, FIELDS_EXTERNAL, and FIELDS_AGGREGATE
- **THEN** `getField()` throws an `Exception` with a message indicating the field is defined in multiple field arrays

#### Scenario: Field defined in exactly one array
- **WHEN** a field name exists in exactly one of the four field arrays
- **THEN** `getField()` resolves the field normally without error

### Requirement: Id field excluded from all field arrays
When `getField()` resolves the id field, it SHALL verify the id field name is not declared in any of FIELDS, FIELDS_READONLY, FIELDS_EXTERNAL, or FIELDS_AGGREGATE. If it appears in any array, it SHALL throw an `Exception` whose message indicates the id field is managed automatically.

#### Scenario: Id declared in FIELDS
- **WHEN** the id field name is present in FIELDS
- **THEN** `getField()` throws an `Exception` indicating the id field must not be declared in field arrays

#### Scenario: Id declared in FIELDS_READONLY
- **WHEN** the id field name is present in FIELDS_READONLY
- **THEN** `getField()` throws an `Exception` indicating the id field must not be declared in field arrays

#### Scenario: Id declared in FIELDS_EXTERNAL
- **WHEN** the id field name is present in FIELDS_EXTERNAL
- **THEN** `getField()` throws an `Exception` indicating the id field must not be declared in field arrays

#### Scenario: Id declared in FIELDS_AGGREGATE
- **WHEN** the id field name is present in FIELDS_AGGREGATE
- **THEN** `getField()` throws an `Exception` indicating the id field must not be declared in field arrays

#### Scenario: Id not declared in any field array
- **WHEN** the id field name is absent from all four field arrays
- **THEN** `getField()` resolves the id field using ID_TYPE without error