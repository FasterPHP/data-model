# repository-paginator-defaults Specification

## Purpose

Defines which paginator a repository queries through, and whether application-wide static paginator
defaults apply to it, so that pagination is something a caller opts into rather than something a
repository acquires silently.

## Requirements

### Requirement: Repository-internal paginators are unlimited

When a `Repository` is constructed without a paginator, it SHALL build one whose maximum items per
page is explicitly `null`. Application-wide static defaults SHALL NOT apply to a paginator built this
way, whether the caller passed a `Sort` or passed nothing at all.

#### Scenario: Repository constructed with neither paginator nor sort
- **WHEN** a `Repository` is constructed with only a PDO instance
- **AND** a static default maximum items per page has been set
- **THEN** the repository's paginator SHALL report a maximum items per page of `null`
- **AND** generated SQL SHALL contain no `LIMIT` clause

#### Scenario: Repository constructed with a Sort
- **WHEN** a `Repository` is constructed with a PDO instance and a `Sort`
- **AND** a static default maximum items per page has been set
- **THEN** the repository's paginator SHALL report a maximum items per page of `null`
- **AND** generated SQL SHALL contain an `ORDER BY` clause and no `LIMIT` clause

#### Scenario: Set fetched from an unpaginated repository is not truncated
- **WHEN** a repository constructed without a paginator fetches a Set whose result exceeds the static
  default page size
- **THEN** the Set SHALL contain every matching row

#### Scenario: No static default set
- **WHEN** a `Repository` is constructed without a paginator and no static default has been set
- **THEN** the repository's paginator SHALL report a maximum items per page of `null`

### Requirement: Explicitly supplied paginators are used unchanged

When a `Repository` is constructed with a paginator, it SHALL use that paginator as given and SHALL
NOT alter its page size, page number or sort. Whether static defaults apply to that paginator is
determined solely by how the paginator itself was configured before it was passed in.

#### Scenario: Supplied paginator inherits the static default
- **WHEN** a paginator is constructed, its maximum items per page is never set, and it is passed to a
  `Repository`
- **AND** a static default maximum items per page has been set
- **THEN** the repository SHALL page results according to that static default

#### Scenario: Supplied paginator with an explicit page size
- **WHEN** a paginator with an explicitly set maximum items per page is passed to a `Repository`
- **THEN** the repository SHALL page results according to that explicit value

#### Scenario: Supplied paginator is not reconfigured by the repository
- **WHEN** a paginator is passed to a `Repository`
- **THEN** the paginator's maximum items per page, page number and sort SHALL be unchanged by
  construction

### Requirement: Pagination remains reachable after construction

A repository constructed without a paginator SHALL still be able to have a page size applied
afterwards through its existing fluent configuration, so that opting into pagination does not require
constructing a paginator.

#### Scenario: Page size applied after construction
- **WHEN** a repository constructed without a paginator has its maximum items per page set to a
  positive integer
- **THEN** subsequent queries SHALL be limited to that number of rows

#### Scenario: Sort applied after construction
- **WHEN** a repository constructed without a paginator has a sort applied
- **THEN** subsequent queries SHALL be ordered accordingly and SHALL remain unlimited
