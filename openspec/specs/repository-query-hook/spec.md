# repository-query-hook Specification

## Purpose

Defines the extension point through which a repository's SELECT is built, so that a subclass can
adjust one clause, replace the whole query with hand-written SQL, or change nothing at all, without
losing sorting, pagination or Item construction.

## Requirements

### Requirement: Repository SELECTs are built through a single query hook

A repository SHALL build the query for every Set and Item retrieval through one overridable method
that returns a query. Retrieval SHALL NOT assemble SQL by any other route.

#### Scenario: Set retrieval uses the hook
- **WHEN** a Set is retrieved with filters
- **THEN** the executed SQL SHALL be the SQL of the query returned by the hook

#### Scenario: Item retrieval uses the hook
- **WHEN** a single Item is retrieved with filters
- **THEN** the executed SQL SHALL be the SQL of the query returned by the hook

#### Scenario: Filters reach the hook
- **WHEN** retrieval is called with filters and search types
- **THEN** the hook SHALL receive those filters and search types

### Requirement: The default query composes the existing clause hooks

The default implementation of the query hook SHALL build its clauses from the existing select, from,
group by, where and having hooks, producing the same SQL as before this capability existed. A subclass
overriding only a clause hook SHALL see its override reflected in the built query.

#### Scenario: Unmodified repository produces unchanged SQL
- **WHEN** a repository that overrides no hook retrieves a Set
- **THEN** the executed SQL SHALL be equivalent to the SQL produced before the query hook existed

#### Scenario: Overridden select clause is reflected
- **WHEN** a repository overrides the select clause hook to add a computed column
- **THEN** the built query's select clause SHALL contain that column
- **AND** the executed SQL SHALL contain it

#### Scenario: Overridden from clause is reflected
- **WHEN** a repository overrides the from clause hook to add a join
- **THEN** the built query's from clause SHALL contain that join
- **AND** the executed SQL SHALL contain it

#### Scenario: Overriding both clause hooks together
- **WHEN** a repository overrides both the select and from clause hooks, as a joined repository does
- **THEN** both overrides SHALL appear in the executed SQL
- **AND** filters SHALL still be applied as WHERE or HAVING according to field ownership

### Requirement: A hand-written query is a supported substitute

A subclass SHALL be able to return a query it constructed itself from the hook, in place of the
default composition. Such a query SHALL be executed as given, and SHALL receive the same sorting,
pagination and Item construction as a default one.

#### Scenario: Hand-written query is executed as given
- **WHEN** a repository returns a query it constructed itself
- **THEN** the executed SQL SHALL be that query's SQL
- **AND** the clause hooks SHALL NOT contribute to it

#### Scenario: Hand-written query is sorted and paginated
- **WHEN** a repository returning a hand-written query has a sort and a page size applied
- **THEN** the executed SQL SHALL carry the corresponding ORDER BY and LIMIT
- **AND** the results SHALL be returned as Items of the repository's Item class

#### Scenario: Hand-written query binds its own parameters
- **WHEN** a repository returns a hand-written query whose clauses bind parameters
- **THEN** those parameters SHALL be bound on execution

#### Scenario: Hand-written query is subject to the same parameter guarantees
- **WHEN** a hand-written query's clauses bind the same parameter name to different values
- **THEN** an `Exception` SHALL be thrown rather than a binding being discarded

### Requirement: Queries are handed to execution intact

A built query SHALL be passed to execution as a single value. Execution SHALL NOT receive a query's
SQL and its parameters through separate steps, so no intermediate state can exist in which the two
disagree.

#### Scenario: Execution receives one query
- **WHEN** a built query is executed
- **THEN** its SQL and parameters SHALL be supplied together

#### Scenario: Executing a different query replaces both
- **WHEN** a paginator that has executed one query is given a different one
- **THEN** both the SQL and the parameters SHALL be those of the new query
- **AND** cached results from the previous query SHALL be discarded
