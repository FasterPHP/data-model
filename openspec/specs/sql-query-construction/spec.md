# sql-query-construction Specification

## Purpose

Defines the value objects that carry a SELECT query and its clauses between construction and
execution, so that a query's SQL and the parameters it binds cannot become separated or fall out of
step with one another.

## Requirements

### Requirement: A clause carries its own parameters

A clause SHALL hold a SQL fragment together with the parameters that fragment binds. A clause with no
parameters SHALL be valid. A clause SHALL be immutable once constructed.

#### Scenario: Clause exposes its fragment and parameters
- **WHEN** a clause is constructed from a SQL fragment and a parameter map
- **THEN** both SHALL be readable from it unchanged

#### Scenario: Clause with no parameters
- **WHEN** a clause is constructed from a SQL fragment alone
- **THEN** its parameters SHALL be empty

#### Scenario: Clause cannot be altered after construction
- **WHEN** an attempt is made to change a clause's fragment or parameters
- **THEN** the attempt SHALL fail rather than modify the clause

### Requirement: A query exposes every clause it holds

A query SHALL expose each clause of a SELECT independently: the select list, the from source, and the
optional where, group by and having clauses. An absent optional clause SHALL be distinguishable from
an empty one.

#### Scenario: Required clauses are readable
- **WHEN** a query is constructed with a select clause and a from clause
- **THEN** both SHALL be readable from it

#### Scenario: Optional clauses are readable when present
- **WHEN** a query is constructed with where, group by and having clauses
- **THEN** each SHALL be readable from it

#### Scenario: Absent optional clauses are reported as absent
- **WHEN** a query is constructed without a where clause
- **THEN** reading the where clause SHALL report its absence rather than an empty fragment

### Requirement: A query is immutable and derives rather than mutates

A query SHALL be immutable. Replacing a clause SHALL produce a new query and SHALL leave the original
unchanged, so that a query passed to another object cannot be altered by it.

#### Scenario: Replacing a clause returns a new query
- **WHEN** a clause is replaced on a query
- **THEN** a new query SHALL be returned carrying the replacement
- **AND** the original query SHALL still carry its previous clause

#### Scenario: Unreplaced clauses are carried over
- **WHEN** one clause is replaced on a query holding several
- **THEN** the returned query SHALL carry the replacement for that clause and the original values for
  the others

#### Scenario: Replacing several clauses at once
- **WHEN** more than one clause is replaced in a single derivation
- **THEN** the returned query SHALL carry every replacement

#### Scenario: Removing an optional clause
- **WHEN** an optional clause is removed by derivation
- **THEN** the returned query SHALL report that clause as absent

### Requirement: Query SQL and parameters are produced together

A query SHALL produce its complete SQL and the full set of parameters that SQL binds as a single
coherent result, assembled from its clauses. It SHALL NOT be possible to obtain SQL from a query
without the parameters belonging to it.

#### Scenario: Assembled SQL includes every present clause
- **WHEN** a query holding select, from, where, group by and having clauses is rendered
- **THEN** the SQL SHALL contain each clause, introduced by its keyword, in SELECT, FROM, WHERE,
  GROUP BY, HAVING order

#### Scenario: Absent clauses contribute no keyword
- **WHEN** a query without a where clause is rendered
- **THEN** the SQL SHALL contain no `WHERE` keyword

#### Scenario: Every placeholder has a binding
- **WHEN** a query is rendered
- **THEN** every placeholder appearing in the SQL SHALL have an entry in the accompanying parameters

#### Scenario: Every binding belongs to a rendered clause
- **WHEN** a query is rendered
- **THEN** every accompanying parameter SHALL derive from a clause present in the rendered SQL

#### Scenario: Colliding parameter names across clauses are rejected
- **WHEN** a query is rendered whose clauses bind the same parameter name to different values
- **THEN** an `Exception` SHALL be thrown identifying the colliding name
- **AND** no binding SHALL be silently discarded

### Requirement: A query can AND a condition onto its WHERE or HAVING clause

A query SHALL be able to derive a new query in which a given condition is ANDed onto its WHERE
clause, and likewise onto its HAVING clause. When the clause is absent, the condition SHALL become the
clause as given. When the clause is present, the derived clause SHALL require both the existing
condition and the new one, with each grouped so that neither's operators bind to the other. The
parameters of both SHALL be carried, and the original query SHALL be left unchanged.

#### Scenario: Condition becomes an absent clause
- **WHEN** a condition is ANDed onto a query that has no WHERE clause
- **THEN** the derived query's WHERE clause SHALL be that condition, ungrouped, with its parameters

#### Scenario: Condition is combined with a present clause
- **WHEN** a condition is ANDed onto a query whose WHERE clause is `a = :a OR b = :b`
- **THEN** the derived query's WHERE clause SHALL be `(a = :a OR b = :b) AND (<condition>)`
- **AND** it SHALL carry the parameters of both

#### Scenario: HAVING is combined in the same way
- **WHEN** a condition is ANDed onto a query's HAVING clause
- **THEN** the derived HAVING clause SHALL follow the same rules as WHERE
- **AND** the WHERE clause SHALL be unchanged

#### Scenario: The original query is unchanged
- **WHEN** a condition is ANDed onto a query
- **THEN** the original query SHALL still carry its previous clause and parameters

#### Scenario: An empty condition leaves the clause unchanged
- **WHEN** a condition whose SQL is empty and which binds no parameters is ANDed onto a query
- **THEN** the derived query's clause SHALL be the same as the original's, present or absent

#### Scenario: A shared parameter bound to the same value is accepted
- **WHEN** the condition and the existing clause bind the same parameter name to the same value
- **THEN** the derived clause SHALL carry that parameter once

#### Scenario: A shared parameter bound to different values is rejected
- **WHEN** the condition and the existing clause bind the same parameter name to different values
- **THEN** an `Exception` SHALL be thrown identifying the colliding name
- **AND** no binding SHALL be silently discarded
