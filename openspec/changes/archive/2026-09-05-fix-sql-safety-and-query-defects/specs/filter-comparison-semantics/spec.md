## Purpose

Defines the SQL fragment and bound parameters the library produces for each combination of
search type and filter value shape, so that null values, array values and repeated keys have
predictable and correct meaning.

## ADDED Requirements

### Requirement: Null handling for equality and inequality

A `null` filter value SHALL be compared using SQL null semantics rather than being bound as a
parameter. `EQUALS` with `null` SHALL produce `IS NULL`. `NOT_EQUALS` with `null` SHALL produce
`IS NOT NULL`. Neither SHALL bind a parameter.

#### Scenario: EQUALS with null produces IS NULL
- **WHEN** a comparison is built for a key with search type `EQUALS` and value `null`
- **THEN** the SQL fragment SHALL be the quoted identifier followed by `IS NULL`
- **AND** no parameter SHALL be bound

#### Scenario: NOT_EQUALS with null produces IS NOT NULL
- **WHEN** a comparison is built for a key with search type `NOT_EQUALS` and value `null`
- **THEN** the SQL fragment SHALL be the quoted identifier followed by `IS NOT NULL`
- **AND** no parameter SHALL be bound

#### Scenario: Null is never cast to empty string
- **WHEN** a comparison is built with value `null` for any search type
- **THEN** no bound parameter SHALL have the value `''`

### Requirement: Array value handling

An array filter value with search type `EQUALS` SHALL produce an `IN (...)` fragment over the
non-null members, with one bound parameter per member. When the array also contains `null`, the
fragment SHALL additionally match null values. An empty array SHALL produce a fragment that
matches no rows.

#### Scenario: Array of values produces IN clause
- **WHEN** a comparison is built for search type `EQUALS` with a non-empty array of non-null values
- **THEN** the SQL fragment SHALL be an `IN (...)` clause listing one placeholder per value
- **AND** one parameter SHALL be bound per value

#### Scenario: Array containing null also matches null
- **WHEN** a comparison is built for search type `EQUALS` with an array containing both non-null
  values and `null`
- **THEN** the SQL fragment SHALL match the non-null values via `IN (...)` or an equality
  comparison, and SHALL additionally match rows where the column is null

#### Scenario: Array containing only null produces IS NULL
- **WHEN** a comparison is built for search type `EQUALS` with an array whose only member is `null`
- **THEN** the SQL fragment SHALL be the quoted identifier followed by `IS NULL`
- **AND** no parameter SHALL be bound

#### Scenario: Empty array matches no rows
- **WHEN** a comparison is built for search type `EQUALS` with an empty array
- **THEN** the SQL fragment SHALL match no rows
- **AND** the fragment SHALL NOT be `IS NULL`
- **AND** no parameter SHALL be bound

#### Scenario: Duplicate values are not bound twice
- **WHEN** a comparison is built for search type `EQUALS` with an array containing repeated values
- **THEN** each distinct value SHALL be bound once

### Requirement: Placeholder uniqueness

Every distinct filter key within a single query SHALL be assigned a distinct parameter
placeholder, and no binding SHALL be silently discarded when placeholders are combined. Keys
that differ only in characters that are not valid in a placeholder name SHALL NOT collide.

#### Scenario: Keys differing only in punctuation do not collide
- **WHEN** a query is built with filter keys `user.id` and `user_id`
- **THEN** the two comparisons SHALL use different placeholders
- **AND** both values SHALL be present in the bound parameters

#### Scenario: WHERE and HAVING parameters are both preserved
- **WHEN** a query is built with filters that produce both `WHERE` and `HAVING` fragments
- **THEN** the combined parameter set SHALL contain a binding for every placeholder appearing
  in either fragment

#### Scenario: Every placeholder in the SQL has a binding
- **WHEN** a query is built with any combination of filter keys
- **THEN** every placeholder appearing in the generated SQL SHALL have a corresponding entry in
  the bound parameters
