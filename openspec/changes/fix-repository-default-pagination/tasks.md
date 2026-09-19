## 1. Baseline characterisation

- [x] 1.1 Add a test to `tests/RepositoryTest.php` recording that a repository constructed with only a PDO currently reports the static default page size, and that one constructed with a `Sort` does the same; verify with `vendor/bin/phpunit --no-coverage --filter RepositoryTest` and confirm both assertions describe the pre-change behaviour
- [x] 1.2 Add a test to `tests/RepositoryPdoTest.php` recording the SQL currently executed by `getItemWithParams()` on a bare repository, showing the page-sized `LIMIT` rather than `LIMIT 1`; verify with `vendor/bin/phpunit --no-coverage --filter RepositoryPdoTest`

## 2. Repository-internal paginators are unlimited

- [x] 2.1 Set the page size explicitly to `null` on the paginator `Repository::__construct()` builds when the caller supplies none, for both the `Sort` and the no-argument cases; verify the four scenarios of the "Repository-internal paginators are unlimited" requirement pass, inverting the characterisation test from 1.1
- [x] 2.2 Add a test asserting that generated SQL for a bare repository contains no `LIMIT` clause while a static default is set, and that a `Sort`-constructed repository produces `ORDER BY` without `LIMIT`; verify with `vendor/bin/phpunit --no-coverage --filter RepositoryPdoTest`
- [x] 2.3 Add a test asserting a Set fetched from a bare repository returns more rows than the static default page size when the underlying result exceeds it; verify with `vendor/bin/phpunit --no-coverage --filter Repository`
- [x] 2.4 Add tests covering the "Explicitly supplied paginators are used unchanged" requirement, including that a supplied paginator still inherits the static default and that its page size, page number and sort are unchanged by construction; verify with `vendor/bin/phpunit --no-coverage --filter Repository`
- [x] 2.5 Add tests covering the "Pagination remains reachable after construction" requirement, confirming `setMaxItemsPerPage()` and `setSort()` on a bare repository still take effect; verify with `vendor/bin/phpunit --no-coverage --filter Repository`

## 3. Single-item retrieval limit

- [x] 3.1 Change `getItemWithParams()` to run its query through a short-lived `SqlPaginator` with a page size of one, inheriting the repository's current sort and leaving the repository's own paginator untouched; verify the "Single-item lookups fetch at most one row" scenarios pass, inverting the characterisation test from 1.2
- [ ] 3.2 Add a test asserting the executed SQL for a single-item lookup contains `LIMIT 1` on both a bare repository and one with an explicit page size; verify with `vendor/bin/phpunit --no-coverage --filter RepositoryPdoTest`
- [ ] 3.3 Add a test asserting a single-item lookup on a sorted repository applies that sort and returns the first row under it; verify with `vendor/bin/phpunit --no-coverage --filter Repository`
- [ ] 3.4 Add tests covering the "Single-item lookups do not disturb repository state" requirement, including that cached paginator figures survive a lookup and that a lookup raising an exception leaves the page size unchanged; verify with `vendor/bin/phpunit --no-coverage --filter Repository`
- [ ] 3.5 Confirm `getItemWithId()` inherits the one-row limit through its delegation to `getItemWithParams()` rather than gaining its own code path; verify by asserting `LIMIT 1` in the SQL it executes

## 4. Documentation and final verification

- [ ] 4.1 Check `README.md` and `examples/` for any statement implying that repository-internal paginators inherit the static defaults, and correct it; verify by grepping the docs for the paginator default setters and reading each hit
- [ ] 4.2 Run `vendor/bin/phpcs` and confirm zero violations
- [ ] 4.3 Run `vendor/bin/phpunit --no-coverage` and confirm the full suite passes with both characterisation tests from group 1 now asserting the corrected behaviour

## 5. Consumer audit

- [ ] 5.1 Review each of the bare-repository `getSet*` call sites in the consuming application and confirm none depended on the 15-row cap; verify by listing each call site with the expected row count and flagging any where an unbounded result would be a problem
- [ ] 5.2 Confirm the consumer's bulk child-record fetches now return every row rather than the first 15; verify against a parent with more than 15 children, or by asserting the absence of a `LIMIT` in the queries it issues
