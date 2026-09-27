# single-item-retrieval-limit Specification

## Purpose

Defines how many rows a single-item lookup fetches from the database, so that retrieving one item
never costs a full result set regardless of how the repository is paginated.

## Requirements

### Requirement: Single-item lookups fetch at most one row

A lookup that returns a single Item or `null` SHALL limit the query to one row. This SHALL hold
regardless of the repository's paginator configuration, including when the repository is unlimited
and when a filter matches many rows.

A lookup by parameters SHALL accept an optional sort that applies to that lookup alone. When one is
given, the lookup SHALL be ordered by it and SHALL NOT also apply the repository's sort. When none is
given, the lookup SHALL be ordered by the repository's sort, if it has one.

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
  without a sort of its own
- **THEN** the executed SQL SHALL apply that sort
- **AND** the returned Item SHALL be the first row under that sort

#### Scenario: A per-call sort orders the lookup
- **WHEN** a single Item is retrieved by non-unique parameters with a sort of `id` descending
- **THEN** the executed SQL SHALL order by `id` descending and limit the result to one row
- **AND** the returned Item SHALL be the row with the highest id among those matching

#### Scenario: A per-call sort replaces the repository's sort for that lookup
- **WHEN** a repository has a sort applied and a single Item is retrieved with a different sort of
  its own
- **THEN** the executed SQL SHALL order only by the per-call sort, including any secondary sort it
  carries
- **AND** the repository's sort SHALL NOT appear in the executed SQL

### Requirement: Single-item lookups do not disturb repository state

A single-item lookup SHALL NOT alter the repository's own paginator. Page size, page number, sort,
and any cached result figures SHALL be the same after the lookup as before it, including when the
lookup throws, and including when the lookup was given a sort of its own.

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

#### Scenario: A per-call sort does not change the repository's sort
- **WHEN** a single-item lookup is performed with a sort of its own, on a repository with a
  different sort or with none
- **THEN** the repository's sort SHALL be the same afterwards as before
- **AND** a subsequent Set retrieval SHALL be ordered by the repository's sort, not the per-call one

#### Scenario: A failing lookup with a per-call sort leaves the repository's sort unchanged
- **WHEN** a single-item lookup given a sort of its own raises an exception during execution
- **THEN** the repository's sort SHALL be the same afterwards as before
