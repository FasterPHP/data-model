## Why

A single-item lookup often needs an order to mean anything: "the latest attempt", "the most recent
login". The only way to give `getItemWithParams()` an order today is `setSort()` on the repository,
which changes the repository's own paginator. The lookup returns the right row, but every later
retrieval through the same instance is then sorted that way too, including Set retrievals that
expected the repository's original order. A known consumer has two finder methods written exactly
like this (`setSort(new Sort('id', Sort::DESCENDING))->getItemWithParams([...])`). They do no harm at
present only because each caller happens to construct a fresh repository.

It is also at odds with a guarantee the library already makes: the `single-item-retrieval-limit`
capability promises that a single-item lookup leaves the repository's page size, page number, sort
and cached figures as they were. The lookup keeps that promise, but the only way to order it forces
the caller to break the same promise one line earlier.

`getItemWithParams()` already runs on a throwaway one-row paginator that copies the repository's
sort, so an order that applies to one lookup only needs somewhere to be passed in.

## What Changes

- `getItemWithParams()` gains an optional third argument, `?Sort $sort = null`. When given, it
  orders that lookup alone and replaces the repository's sort for it; the repository's own sort is
  never read or changed. When omitted, the lookup inherits the repository's sort exactly as today.
- `RepositoryInterface::getItemWithParams()` gains the same optional argument.
- The README documents the "latest row" pattern, `getItemWithParams([...], sort: new Sort('id',
  Sort::DESCENDING))`, in place of calling `setSort()` for a single lookup.
- **BREAKING** only for code that overrides `getItemWithParams()` or implements
  `RepositoryInterface` directly: the signature must add the optional argument. PHP reports the
  mismatch when the class is loaded. No known consumer does either. Callers are unaffected.

Not in this change: `getItemWithId()` keeps its signature, since a lookup by id matches one row and
needs no order. `getSetWithParams()` and `getSetOfAll()` keep using the repository's sort, which is
deliberately persistent: it is the list's order, shared with the paginator the caller renders.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `single-item-retrieval-limit`: a single-item lookup can be ordered for that call alone, and doing
  so leaves the repository's sort untouched.

## Impact

- `src/Repository.php`: `getItemWithParams()`.
- `src/RepositoryInterface.php`: the method signature and docblock.
- Tests: per-call sort applied, repository sort not applied when a per-call sort is given, the
  repository sort unchanged afterwards (including after an exception), and omission behaving as
  today; the existing single-item tests must pass unchanged.
- Documentation: `README.md`.
- Consumers: a known consumer's two finder methods can pass the sort per call instead of calling
  `setSort()`, once they take this release. That is a follow-up in the consumer, not part of this
  change.
- Release: ships as 1.0.0-rc3.
