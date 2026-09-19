# single-item-retrieval-limit Specification

## Purpose

Defines how many rows a single-item lookup fetches from the database, so that retrieving one item
never costs a full result set regardless of how the repository is paginated.

## Requirements

### Requirement: Single-item lookups fetch at most one row

A lookup that returns a single Item or `null` SHALL limit the query to one row. This SHALL hold
regardless of the repository's paginator configuration, including when the repository is unlimited
and when a filter matches many rows.

#### Scenario: Lookup by id fetches one row
- **WHEN** an Item is retrieved by id
- **THEN** the executed SQL SHALL limit the result to one row

#### Scenario: Lookup by non-unique params fetches one row
- **WHEN** an Item is retrieved by parameters matching many rows
- **THEN** the executed SQL SHALL limit the result to one row
- **AND** the returned Item SHALL be the first row in the query's order

#### Scenario: Lookup on an unlimited repository still fetches one row
- **WHEN** a repository constructed without a paginator retrieves a single Item
- **THEN** the executed SQL SHALL limit the result to one row

#### Scenario: Lookup matching no rows returns null
- **WHEN** a single-item lookup matches no rows
- **THEN** the result SHALL be `null`

#### Scenario: Repository sort is honoured by single-item lookups
- **WHEN** a repository has a sort applied and a single Item is retrieved by non-unique parameters
- **THEN** the executed SQL SHALL apply that sort
- **AND** the returned Item SHALL be the first row under that sort

### Requirement: Single-item lookups do not disturb repository state

A single-item lookup SHALL NOT alter the repository's own paginator. Page size, page number, sort,
and any cached result figures SHALL be the same after the lookup as before it, including when the
lookup throws.

#### Scenario: Paginator page size is unchanged by a lookup
- **WHEN** a repository with an explicit page size performs a single-item lookup
- **THEN** the repository's paginator SHALL still report that page size afterwards

#### Scenario: Cached paginator figures survive a lookup
- **WHEN** a repository's paginator has cached result figures from a previous Set query
- **AND** a single-item lookup is then performed
- **THEN** those cached figures SHALL be unchanged

#### Scenario: A failing lookup leaves no limit behind
- **WHEN** a single-item lookup raises an exception during execution
- **THEN** the repository's paginator SHALL report the same page size as before the lookup
