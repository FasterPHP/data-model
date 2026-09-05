## Purpose

Defines which cached paginator values are discarded when the inputs they are derived from
change, so that a paginator reused across queries or reconfigured mid-request never reports
figures computed from a previous configuration.

## ADDED Requirements

### Requirement: Derived page count is invalidated with its inputs

`getNumPages()` is derived from the total item count and the maximum items per page. Any change
to either input SHALL invalidate the cached page count, so that the next call recomputes it.

#### Scenario: Changing page size invalidates page count
- **WHEN** `getNumPages()` has been called
- **AND** `setMaxItemsPerPage()` is then called with a different value
- **THEN** the next call to `getNumPages()` SHALL return a value computed from the new page size

#### Scenario: Changing the query invalidates page count
- **WHEN** `getNumPages()` has been called on a paginator
- **AND** the paginator's SQL or parameters are then changed to a different query
- **THEN** the next call to `getNumPages()` SHALL return a value computed from the new query's
  total item count

#### Scenario: Setting a new total invalidates page count
- **WHEN** `getNumPages()` has been called
- **AND** `setNumItemsTotal()` is then called with a different value
- **THEN** the next call to `getNumPages()` SHALL return a value computed from the new total

#### Scenario: Page count is stable when inputs are unchanged
- **WHEN** `getNumPages()` is called twice with no intervening change to the total item count,
  the page size, or the query
- **THEN** both calls SHALL return the same value
- **AND** the total item count SHALL NOT be queried a second time

### Requirement: Result invalidation is complete

When cached query results are discarded because the query changed, every value derived from
those results SHALL be discarded with them, leaving no cached value that describes the previous
query.

#### Scenario: Changing the query discards all derived values
- **WHEN** a paginator has cached items, an item count for the current page, a total item count
  and a page count
- **AND** the query is then changed
- **THEN** none of those cached values SHALL be returned for the new query

#### Scenario: Reused paginator reports the second query's figures
- **WHEN** a single paginator instance is used to execute one query and then a second query
  returning a different number of rows
- **THEN** the item count, total count and page count SHALL all describe the second query
