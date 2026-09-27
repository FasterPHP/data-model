## ADDED Requirements

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
