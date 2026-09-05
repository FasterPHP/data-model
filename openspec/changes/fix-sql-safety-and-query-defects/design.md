## Context

See proposal.md - Why. The relevant current state is that `Sql` is a stateless static helper
holding the only identifier-quoting and placeholder-naming logic in the library, and that
`Repository::getComparison()` and `Sort` are the two entry points through which a
caller-supplied name reaches it. `Sql::likeWildcards()` currently reads search-type constants
from `Repository`, so the low-level helper depends on the high-level class that calls it.

The two known consumers allowlist filter keys and sort fields before they reach this library.
The library therefore has no live exposure, but also no independent guarantee: it fails unsafely
the first time a consumer omits an allowlist.

## Goals / Non-Goals

**Goals:**

- A single choke point that every generated identifier passes through, so no future call path
  can emit an unvalidated identifier by omission.
- Filter comparison output that is correct for null and array values without the caller needing
  to know which shapes are mishandled.
- Placeholder names that are injective with respect to filter keys, so no binding can be lost.
- Cache invalidation that is complete by construction rather than by remembering to unset each
  derived value.

**Non-Goals:**

- Restricting filter keys to declared fields. Consumers legitimately filter on joined-table and
  aliased columns (`users.userId`, `a.courseId`) that are not declared in any field array, and
  `aggregate-field-qualification` already specifies that such keys are used bare.
- Changing the `array $params` / `array $types` filter API shape.
- Removing the static paginator defaults, or altering the shared mutable paginator in
  `Repository::fetchData()`. Both are deferred to a later change.

## Decisions

### Validate inside `Sql::ident()`, not at each call site

Identifier validation lives in `Sql::ident()` itself, so validation is a property of quoting
rather than something each caller must remember.

*Alternative considered*: validating separately in `getComparison()` and `Sort::setSortField()`.
Rejected because it leaves `Sql::ident()` unsafe for any future caller, and the library already
has two call sites that were each missed once.

### Keep escaping as well as validation

The permitted identifier shape excludes backticks, so escaping is unreachable in normal
operation. It is retained anyway: `ident()` is a public static method, escaping is one
`str_replace`, and the combination means a future relaxation of the shape rule cannot silently
become an injection. Validation is the primary control; doubling backticks is the secondary one.

### Permitted shape: dot-separated alphanumeric-and-underscore segments

One or more non-empty segments of `[A-Za-z0-9_]`, separated by single dots. This admits every
key and sort field used by the known consumers, including qualified and aliased columns, while
excluding whitespace, parentheses, commas, quotes and operators.

*Alternative considered*: allowing a configurable pattern or an escape hatch for expressions.
Rejected as scope creep; a subclass that needs an expression should override the clause hook
rather than smuggle SQL through a filter key.

### Placeholder names change only where sanitisation was lossy

`Sql::placeholder()` stays stateless and pure. Where the sanitised name equals the original key,
the placeholder is unchanged, so `user_id` remains `:user_id`. Where sanitisation altered the
key, a short deterministic suffix derived from the original key is appended, making the mapping
injective. `user.id` and `user_id` therefore no longer collide.

*Alternative considered*: threading a per-query counter or registry through `getArgsSqlAndParams()`
to disambiguate on collision. Rejected because it makes placeholder names depend on filter
ordering, which is worse for debugging and for anyone comparing generated SQL between runs.

As a secondary control, the `$whereParams + $havingParams` union and the `$params += $chunk`
accumulation are replaced with a merge that throws when a key would be overwritten. With
injective placeholders this should be unreachable, so it functions as an assertion rather than
as behaviour.

### Empty array reuses the existing `1 = 0` fragment

`Sql::expandIn()` already returns `1 = 0` for the empty case. The empty-array path in
`getComparison()` returns before reaching it, which is the whole defect. The fix routes the
empty case to the same fragment rather than introducing a second spelling of "matches nothing".

### A single invalidation helper on `Paginator\Base`

Rather than adding `unset($this->numPages)` to each of `clearResults()`, `setMaxItemsPerPage()`
and `setPageNum()`, `Base` gains one protected method that discards every derived value, and the
three call sites use it. The `numPages` bug exists precisely because invalidation was duplicated
in three places and one was missed; concentrating it means the next derived value added is
handled once.

Invalidation is conditional on the input actually changing, so a repeated
`setMaxItemsPerPage()` with the same value does not force a second `COUNT(*)` query.

### Search-type constants move to `Sql`, with `Repository` aliasing them

The canonical `STARTS`, `ENDS` and `CONTAINS` values are defined on `Sql`, and the existing
`Repository` constants are redefined to reference them. This inverts the dependency to the
correct direction while keeping `Repository::CONTAINS` valid, which matters because consumers
write `$searchTypes[$field] = BaseRepository::CONTAINS`.

*Alternative considered*: a `SearchType` backed enum. Rejected for this change: the public
filter API takes `array $types` of strings, so adopting an enum is a breaking API change that
belongs with the wider architectural work, not with a defect fix.

## Risks / Trade-offs

- **Narrowing rejects a filter key or sort field some consumer relies on** → Both known consumers
  were audited and neither passes an expression as a key or an unvalidated sort field. The
  exception message includes the rejected value so any missed case is immediately diagnosable
  rather than silently wrong.

- **Changed placeholder names are observable to a subclass that overrode `getComparison()` and
  hardcoded placeholder names** → Names are preserved wherever the key was already a plain
  identifier, so only keys containing punctuation change. No known consumer overrides
  `getComparison()`.

- **Empty-array semantics change could alter results in an application that relied on the current
  `IS NULL` behaviour** → Characterisation tests are written before the change so the existing
  behaviour is recorded, and the reversal is called out as breaking in the proposal. The known
  consumer passes only non-empty arrays and array constants.

- **`NOT_EQUALS` correction changes results for any caller passing null** → The sole consumer use
  passes a non-null id, so it is unaffected. The current output (`!= ''`) is wrong under any
  reading, so preserving it is not an option.

- **Additional validation on a hot path** → One `preg_match` per identifier, on a code path that
  already builds and executes SQL. Not material.

## Migration Plan

1. Write characterisation tests for the current behaviour of `getComparison()` across null,
   array, empty-array and duplicate-key inputs, and for paginator reuse, so the change is made
   against a recorded baseline rather than against assumptions.
2. Land the identifier changes (`Sql::ident()`, `Sort`), then the comparison changes, then the
   paginator changes, then the constant relocation. Each step is independently testable and each
   is committed separately per the checkpoint discipline in `docs/standards/workflow-notes.md`.
3. No consumer change is required. `faster-php/gem` and `Gem/portal2` continue to work unmodified,
   which is what keeps this change independent of the `gem` branch coordination that the wider
   `main` merge needs.

Rollback is a straightforward revert: no data migration, no persisted state, no dependency change.
