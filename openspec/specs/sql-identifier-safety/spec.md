# sql-identifier-safety Specification

## Purpose
Defines how the library escapes and validates SQL identifiers wherever a caller-supplied name
is interpolated into generated SQL, so that a malformed or hostile name cannot alter the
structure of a query.

## Requirements

### Requirement: Backtick escaping in quoted identifiers

`Sql::ident()` SHALL escape backtick characters within each identifier segment by doubling them,
so that the returned string cannot terminate its own quoting. Dot separators SHALL continue to
delimit segments, with each segment quoted independently.

#### Scenario: Plain identifier is quoted
- **WHEN** `Sql::ident()` is called with `name`
- **THEN** it SHALL return `` `name` ``

#### Scenario: Dot-qualified identifier quotes each segment
- **WHEN** `Sql::ident()` is called with `users.name`
- **THEN** it SHALL return `` `users`.`name` ``

#### Scenario: Embedded backtick cannot terminate quoting
- **WHEN** `Sql::ident()` is called with an identifier containing a backtick, and that identifier
  is otherwise of valid shape
- **THEN** the returned string SHALL contain the backtick in doubled form
- **AND** the returned string SHALL NOT permit any character of the input to be interpreted as
  SQL outside the quoted identifier

### Requirement: Identifier shape validation

`Sql::ident()` SHALL accept only an identifier consisting of one or more segments separated by
single dots, where each segment is non-empty and contains only ASCII letters, digits and
underscores. Any other input SHALL cause an `Exception` to be thrown whose message includes the
rejected value.

#### Scenario: Plain identifier is accepted
- **WHEN** `Sql::ident()` is called with `courseId`
- **THEN** it SHALL return the quoted identifier without error

#### Scenario: Dot-qualified identifier is accepted
- **WHEN** `Sql::ident()` is called with `a.courseId`
- **THEN** it SHALL return the quoted identifier without error

#### Scenario: SQL expression is rejected
- **WHEN** `Sql::ident()` is called with a value containing spaces, parentheses, commas or
  quotation marks, such as an aggregate expression or a fragment of SQL
- **THEN** it SHALL throw an `Exception` whose message includes the rejected value

#### Scenario: Empty segment is rejected
- **WHEN** `Sql::ident()` is called with a value having an empty segment, such as a leading,
  trailing or doubled dot
- **THEN** it SHALL throw an `Exception`

#### Scenario: Empty identifier is rejected
- **WHEN** `Sql::ident()` is called with an empty string
- **THEN** it SHALL throw an `Exception`

### Requirement: Sort field validation

`Sort` SHALL validate its sort field against the identifier shape when the field is set,
throwing an `Exception` for any value that would be rejected by `Sql::ident()`. Validation
SHALL occur at construction rather than at SQL generation, so an invalid sort field is
reported at the point it is supplied.

#### Scenario: Valid sort field is accepted
- **WHEN** a `Sort` is constructed with the field `users.userId`
- **THEN** construction SHALL succeed
- **AND** `getSortField()` SHALL return `users.userId`

#### Scenario: Invalid sort field is rejected at construction
- **WHEN** a `Sort` is constructed with a field that is not a valid identifier shape
- **THEN** an `Exception` SHALL be thrown whose message includes the rejected value

#### Scenario: Secondary sort field is validated
- **WHEN** a `Sort` is constructed with a valid primary field and a secondary `Sort` whose
  field is not a valid identifier shape
- **THEN** an `Exception` SHALL be thrown

#### Scenario: Generated ORDER BY contains only validated identifiers
- **WHEN** `SqlPaginator::getSortSql()` generates an `ORDER BY` clause
- **THEN** every identifier in the clause SHALL have passed identifier shape validation
