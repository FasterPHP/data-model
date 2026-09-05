## Purpose

Defines how validation is discovered and run from per-field validator methods on an Item, and how
validation results are collected and reset when a field changes, in place of reading a `VALIDATORS`
class constant.

## Requirements

### Requirement: Validator method discovery
`ValidatableTrait::validate()` SHALL discover validator methods by iterating `static::FIELDS` keys and checking for a method named `validate` + ucfirst(fieldName) on the current instance. Fields without a matching method SHALL be skipped (no validation applied). The `VALIDATORS` class constant SHALL NOT be read.

#### Scenario: Field with a validator method
- **WHEN** an Item subclass defines a method `validateName()` and field `name` exists in `FIELDS`
- **THEN** `validate()` calls `$this->validateName()` and uses the returned chain to validate the field's value

#### Scenario: Field without a validator method
- **WHEN** an Item subclass does not define a `validateAge()` method but field `age` exists in `FIELDS`
- **THEN** `validate()` skips validation for `age` and does not produce errors for it

#### Scenario: No validator methods defined
- **WHEN** an Item subclass uses `ValidatableTrait` but defines no `validate*` methods
- **THEN** `isValid()` returns `true` and `getValidationErrors()` returns an empty array

### Requirement: Validator method signature
Each `validate{FieldName}()` method SHALL be a `protected` instance method that accepts no arguments and returns a `Laminas\Validator\ValidatorChain`. The method has full access to `$this`, including other field values.

#### Scenario: Method returns a ValidatorChain
- **WHEN** `validateName()` returns a `ValidatorChain` with a `StringLength` validator attached
- **AND** the `name` field value is `"x"` (below minimum length)
- **THEN** `isValid()` returns `false`
- **AND** `getValidationErrors()` contains an entry for `name` with the validator's error message

#### Scenario: Method accesses other fields
- **WHEN** `validateEndDate()` reads `$this->getField('startDate')->getValue()` to compare dates
- **THEN** the validation executes without error, enabling cross-field validation

### Requirement: Validation result collection
`ValidatableTrait::validate()` SHALL collect results from each validator method in the same format as the current implementation: `getValidationErrors()` returns an associative array keyed by field name, where each value is a flat array of error message strings. `isValid()` returns `false` if any field fails.

#### Scenario: Multiple fields fail validation
- **WHEN** `validateName()` produces two error messages and `validateAge()` produces one
- **THEN** `getValidationErrors()` returns `['name' => ['msg1', 'msg2'], 'age' => ['msg3']]`
- **AND** `isValid()` returns `false`

#### Scenario: All fields pass validation
- **WHEN** all `validate*` methods return chains that pass
- **THEN** `isValid()` returns `true`
- **AND** `getValidationErrors()` returns `[]`

#### Scenario: Errors not available before validation
- **WHEN** `getValidationErrors()` is called before `isValid()` or `validate()`
- **THEN** an `Exception` with message "Item not validated" SHALL be thrown

### Requirement: Validation state reset on field change
The validation result (`isValid`) SHALL be cleared when a field value changes via a setter, forcing re-validation on the next `isValid()` call.

#### Scenario: Field change invalidates cached result
- **WHEN** `isValid()` returns `true`
- **AND** a field value is changed via a setter
- **THEN** `isValid()` re-runs validation rather than returning the cached result

### Requirement: LaminasValidatorTrait convenience helpers
`LaminasValidatorTrait` SHALL provide helper methods to reduce boilerplate when building validator chains with Laminas validators.

#### Scenario: Create an empty chain
- **WHEN** a `validate*` method calls `$this->createChain()`
- **THEN** a new empty `ValidatorChain` instance is returned

#### Scenario: Attach a validator with a custom message
- **WHEN** a `validate*` method calls `$this->attachValidator($chain, $validator, message: 'Custom error')`
- **THEN** the validator is attached to the chain with its message overridden to "Custom error"

#### Scenario: Attach a validator with break-on-failure
- **WHEN** a `validate*` method calls `$this->attachValidator($chain, $validator, breakOnFailure: true)`
- **THEN** the validator is attached with `breakChainOnFailure` set to `true`

#### Scenario: Attach a validator with priority
- **WHEN** a `validate*` method calls `$this->attachValidator($chain, $validator, priority: 1)`
- **THEN** the validator is attached with priority `1`

### Requirement: ValidatableTrait usable without LaminasValidatorTrait
An Item subclass SHALL be able to use `ValidatableTrait` alone (without `LaminasValidatorTrait`) by building `ValidatorChain` instances directly in its `validate*` methods.

#### Scenario: Direct chain construction without helpers
- **WHEN** an Item uses only `ValidatableTrait` and its `validateName()` method constructs a `ValidatorChain` manually
- **THEN** validation works identically to when `LaminasValidatorTrait` helpers are used
