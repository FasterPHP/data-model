## REMOVED Requirements

### Requirement: Repository SELECTs are built through a single query hook

**Reason**: The hook received the caller's filters and was trusted to apply them, so an override that
left them unused silently returned the wrong rows, including from identity lookups. Replaced by
"Repository SELECTs are built through a single filter-free query hook", under which the repository
applies the filters itself.

**Migration**: Drop the filter and search-type parameters from any override of the hook, and stop
applying filters in it. The repository now applies them to whatever query the hook returns.

## ADDED Requirements

### Requirement: Repository SELECTs are built through a single filter-free query hook

A repository SHALL build the base query for every Set and Item retrieval through one overridable
method that returns a query. The hook SHALL receive no filters: the query it returns SHALL NOT depend
on which retrieval asked for it. Retrieval SHALL NOT assemble SQL by any other route than the query
the hook returns, with the caller's filters applied to it by the repository.

#### Scenario: Set retrieval uses the hook
- **WHEN** a Set is retrieved with filters
- **THEN** the executed SQL SHALL be the SQL of the query returned by the hook, with those filters
  applied

#### Scenario: Item retrieval uses the hook
- **WHEN** a single Item is retrieved with filters
- **THEN** the executed SQL SHALL be the SQL of the query returned by the hook, with those filters
  applied

#### Scenario: The hook receives no filters
- **WHEN** retrieval is called with filters and search types
- **THEN** the hook SHALL be called without them
- **AND** the filters SHALL still constrain the executed SQL

### Requirement: Caller filters are applied to every query the hook returns

The repository SHALL apply the caller's filters to the query returned by the hook, whether that query
is the default composition or hand-written. Filters on non-aggregate fields SHALL be ANDed onto the
query's WHERE condition and filters on aggregate fields onto its HAVING condition. A condition already
present in the returned query SHALL be kept, and SHALL NOT have its meaning changed by the filters
combined with it. A hook override SHALL NOT be able to cause a caller's filter to be omitted.

#### Scenario: Identity lookup on a hand-written query returns the requested row
- **WHEN** a repository returning a hand-written query retrieves a single Item by its id
- **THEN** the executed SQL SHALL constrain the id column to that id
- **AND** only a row with that id SHALL be returned, or none if no such row matches the query

#### Scenario: Filters narrow a hand-written query
- **WHEN** a repository returning a hand-written query retrieves a Set with a filter
- **THEN** the executed SQL SHALL apply that filter in addition to the query's own conditions

#### Scenario: Filters are combined with an existing condition without changing its meaning
- **WHEN** the hook returns a query whose WHERE condition contains `OR` and retrieval is filtered
- **THEN** the executed WHERE condition SHALL require both the query's condition and the filter,
  with each grouped so that neither's operators bind to the other

#### Scenario: Aggregate filters are combined with an existing HAVING condition
- **WHEN** the hook returns a query with a HAVING condition and retrieval filters on an aggregate field
- **THEN** the executed HAVING condition SHALL require both

#### Scenario: No filters leave the query unchanged
- **WHEN** retrieval is called with no filters and the where and having hooks contribute nothing
- **THEN** the executed SQL SHALL be the SQL of the query returned by the hook, before sorting and
  pagination

#### Scenario: A filter parameter colliding with the query's own is rejected
- **WHEN** a filter binds a parameter name the returned query already binds, to a different value
- **THEN** an `Exception` SHALL be thrown identifying the colliding name
- **AND** no query SHALL be executed

#### Scenario: Filters on declared fields are qualified with the table name
- **WHEN** retrieval filters on a field the Item class declares
- **THEN** the filter SHALL reference that column qualified by the repository's table name, so a
  returned query SHALL expose the base table under that name for such filters to resolve

## MODIFIED Requirements

### Requirement: The default query composes the existing clause hooks

The default implementation of the query hook SHALL build its clauses from the existing select, from
and group by hooks. The caller's filters SHALL be applied to it by the repository through the existing
where and having hooks, so that the executed SQL is the same as before this capability existed. A
subclass overriding only a clause hook SHALL see its override reflected in the executed SQL.

#### Scenario: Unmodified repository produces unchanged SQL
- **WHEN** a repository that overrides no hook retrieves a Set, with or without filters
- **THEN** the executed SQL SHALL be byte-for-byte the SQL produced before the query hook existed

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

#### Scenario: Filters injected through the where hook still apply
- **WHEN** a repository overrides the where hook to add conditions the caller did not supply
- **THEN** those conditions SHALL appear in the executed SQL for every retrieval, including one with
  no caller filters
- **AND** the caller's own filters SHALL appear alongside them

### Requirement: A hand-written query is a supported substitute

A subclass SHALL be able to return a query it constructed itself from the hook, in place of the
default composition. Such a query SHALL be executed as given apart from the caller's filters, which
SHALL be applied to it exactly as to a default query. It SHALL receive the same sorting, pagination
and Item construction as a default one.

#### Scenario: Hand-written query is executed as given
- **WHEN** a repository returns a query it constructed itself and retrieval is called without filters
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
