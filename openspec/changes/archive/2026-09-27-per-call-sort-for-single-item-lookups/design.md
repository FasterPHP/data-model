## Context

See proposal.md for motivation.

`getItemWithParams()` builds a new `SqlPaginator` for every call, passing it
`$this->paginator->getSort()`, limits it to one row, sets the filtered query and reads the first row.
The repository's own paginator is only read, never written, which is how the existing
"do not disturb repository state" guarantee is met. `setSort()` on the repository writes the shared
paginator, and Set retrievals use that paginator directly. A `Sort` validates its field names on
construction and can carry a chain of secondary sorts, which the paginator expands into the ORDER BY
list.

## Goals / Non-Goals

**Goals:**

- Let a single lookup be ordered without writing to any repository state.
- Keep every existing call, and every existing single-item test, behaving exactly as today.

**Non-Goals:**

- A per-call sort for Set retrieval. A Set's order is the order of the list the caller pages and
  renders, held on the paginator the caller may be reading figures from; making it per call is a
  different design question.
- A sort argument on `getItemWithId()`: an id matches at most one row.
- Changing the consumer's finder methods; that follows in the consumer once it takes this release.

## Decisions

### 1. An optional third parameter on `getItemWithParams()`

`getItemWithParams(array $params, array $types = [], ?Sort $sort = null)`. The throwaway paginator is
built with `$sort ?? $this->paginator->getSort()`. That is the whole implementation: nothing else in
the lookup changes, and the repository's paginator is still only read.

Callers can name the argument (`sort: new Sort('id', Sort::DESCENDING)`), which avoids passing an
empty `$types` positionally, and is what the documentation shows.

Alternatives considered:

- *A separate method, such as `getFirstItemWithParams($params, Sort $sort, $types = [])`.* A required
  sort would make the intent explicit in the name, but it duplicates the lookup and leaves two ways to
  do one thing. The optional argument keeps one method and costs callers nothing.
- *A temporary `withSort()` clone of the repository.* It would work for Sets as well, but it adds a
  cloning contract to a class holding a PDO and transaction state, for a need that exists only for
  single lookups.
- *Save and restore the sort around the lookup inside consumer code.* Possible today, but it puts a
  `try`/`finally` in every finder and is exactly the boilerplate the library should absorb.

### 2. A per-call sort replaces the repository's sort rather than extending it

"The latest matching row" is `ORDER BY id DESC`. Appending that to a repository sort would make the
repository sort primary and return the wrong row, so a per-call sort is the whole ordering for that
lookup. A caller who wants a tie-breaker chains one with the `Sort`'s secondary sort.

### 3. The interface changes with the class

`RepositoryInterface` declares `getItemWithParams()`, so it gains the same optional argument, keeping
the class and interface in step. A class implementing the interface without extending `Repository`
must add the argument; that is a load-time error rather than a silent difference, and no known
consumer implements the interface directly.

### 4. The documentation recommends sorting by `id`

For the "latest row" pattern the documentation sorts by `id`, the Item's reserved name for its ID.
ORDER BY may use a select alias, so `id` works on joined repositories where the ID column's bare name
could be ambiguous, and it reads the same whatever the ID column is called.

## Risks / Trade-offs

- [An override of `getItemWithParams()` without the new argument fails at load] → Intended to be
  loud; none known; ships in a release candidate.
- [Two ways to order a single lookup remain: `setSort()` and the argument] → `setSort()` is still the
  right tool for a repository's persistent order. The documentation says which to use for a one-off
  lookup and why.

## Migration Plan

1. Ship as 1.0.0-rc3 with the other rc3 changes; the release notes show the before and after of the
   "latest row" pattern.
2. Consumers adopt it in their finder methods at leisure; existing code keeps working.
3. Rollback is a plain revert.
