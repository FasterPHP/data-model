## Purpose

Defines how an explicit null passed to a paginator instance setter overrides the corresponding
static default, and when the static default applies instead, so that unlimited paging can be
requested per instance.

## Requirements

### Requirement: Instance setMaxItemsPerPage(null) overrides static default
When `setMaxItemsPerPage(null)` is called on a paginator instance, `getMaxItemsPerPage()` SHALL return `null` (unlimited), even when a static default has been set via `setDefaultMaxItemsPerPage()`. The static default SHALL only be used when `setMaxItemsPerPage()` has never been called on the instance.

#### Scenario: Explicit null overrides static default
- **WHEN** `setDefaultMaxItemsPerPage(15)` has been called
- **AND** `setMaxItemsPerPage(null)` is called on a paginator instance
- **THEN** `getMaxItemsPerPage()` SHALL return `null`

#### Scenario: Static default used when setter never called
- **WHEN** `setDefaultMaxItemsPerPage(15)` has been called
- **AND** `setMaxItemsPerPage()` has NOT been called on the paginator instance
- **THEN** `getMaxItemsPerPage()` SHALL return `15`

#### Scenario: Explicit integer value still works
- **WHEN** `setDefaultMaxItemsPerPage(15)` has been called
- **AND** `setMaxItemsPerPage(50)` is called on a paginator instance
- **THEN** `getMaxItemsPerPage()` SHALL return `50`

#### Scenario: No static default and no instance value returns null
- **WHEN** no static default has been set
- **AND** `setMaxItemsPerPage()` has NOT been called on the paginator instance
- **THEN** `getMaxItemsPerPage()` SHALL return `null`

### Requirement: Instance setMaxPageLink(null) overrides static default
When `setMaxPageLink(null)` is called on a paginator instance, `getMaxPageLinks()` SHALL return `null`, even when a static default has been set via `setDefaultMaxPageLinks()`. The static default SHALL only be used when `setMaxPageLink()` has never been called on the instance.

#### Scenario: Explicit null overrides static default for page links
- **WHEN** `setDefaultMaxPageLinks(10)` has been called
- **AND** `setMaxPageLink(null)` is called on a paginator instance
- **THEN** `getMaxPageLinks()` SHALL return `null`

#### Scenario: Static default used for page links when setter never called
- **WHEN** `setDefaultMaxPageLinks(10)` has been called
- **AND** `setMaxPageLink()` has NOT been called on the paginator instance
- **THEN** `getMaxPageLinks()` SHALL return `10`
