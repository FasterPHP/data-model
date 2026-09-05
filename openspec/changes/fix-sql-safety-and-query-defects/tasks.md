## 1. Baseline characterisation

- [x] 1.1 Add characterisation tests to `tests/SqlTest.php` recording the current output of `Sql::ident()` for plain, dot-qualified and backtick-containing input, and of `Sql::placeholder()` for `user.id`, `user_id` and `user-id`; verify by running `vendor/bin/phpunit --no-coverage --filter SqlTest` and confirming the collision between the three keys is captured as a failing expectation to be inverted later
- [x] 1.2 Add characterisation tests to `tests/RepositoryTest.php` recording the current `getComparison()` output for `EQUALS` with null, `NOT_EQUALS` with null, an empty array, an array containing null, and an array with duplicate values; verify with `vendor/bin/phpunit --no-coverage --filter RepositoryTest`
- [x] 1.3 Add a characterisation test to `tests/PaginatorBaseTest.php` that calls `getNumPages()`, then `setMaxItemsPerPage()` with a different value, then `getNumPages()` again, recording the current stale result; verify with `vendor/bin/phpunit --no-coverage --filter PaginatorBaseTest`

## 2. Identifier safety

- [x] 2.1 Add backtick doubling to `Sql::ident()` so an identifier segment cannot terminate its own quoting; verify the `sql-identifier-safety` escaping scenarios pass in `tests/SqlTest.php`
- [x] 2.2 Add identifier shape validation to `Sql::ident()`, rejecting anything that is not one or more non-empty `[A-Za-z0-9_]` segments separated by single dots, throwing an `Exception` naming the rejected value; verify the acceptance and rejection scenarios in `tests/SqlTest.php`, including that `a.courseId` and `users.userId` are still accepted
- [ ] 2.3 Validate the sort field in `Sort::setSortField()` against the same shape, including recursion into the secondary sort; verify the sort-field scenarios pass in `tests/SortTest.php` and that an invalid field throws at construction rather than at SQL generation
- [ ] 2.4 Confirm `Repository::getComparison()` rejects non-identifier keys via the now-validating `Sql::ident()` while still accepting unrecognised but well-shaped keys bare; verify with the two new scenarios in `tests/AggregateFieldsRepositoryTest.php` and that the existing table-qualification tests still pass

## 3. Filter comparison semantics

- [ ] 3.1 Change `Repository::getComparison()` so `NOT_EQUALS` with a null value produces `IS NOT NULL` and binds no parameter, inverting the characterisation test from 1.2; verify with `vendor/bin/phpunit --no-coverage --filter RepositoryTest`
- [ ] 3.2 Change the empty-array path in `getComparison()` to return the `1 = 0` fragment from `Sql::expandIn()` instead of `IS NULL`, inverting the characterisation test from 1.2; verify the empty-array scenario passes and that the array-containing-null and only-null scenarios are unchanged
- [ ] 3.3 Confirm the array paths still bind one parameter per distinct value and that duplicates are bound once; verify with the duplicate-values scenario from 1.2

## 4. Placeholder uniqueness

- [ ] 4.1 Make `Sql::placeholder()` injective by appending a short deterministic suffix derived from the original key only when sanitisation altered it, leaving already-valid keys such as `user_id` unchanged; verify the collision characterisation test from 1.1 now shows three distinct placeholders and that `:user_id` is still produced for `user_id`
- [ ] 4.2 Replace the `$params += $chunk` accumulation in `getArgsSqlAndParams()` and the `$whereParams + $havingParams` union in `getDataWithParams()` with a merge that throws on an overwriting key; verify with a test asserting every placeholder appearing in the generated SQL has a binding, across mixed WHERE and HAVING filters
- [ ] 4.3 Run the full suite to confirm no existing test depended on a colliding placeholder name; verify with `vendor/bin/phpunit --no-coverage`

## 5. Paginator cache invalidation

- [ ] 5.1 Add a protected invalidation method to `Paginator\Base` that discards every derived cached value including `numPages`, and call it from `setMaxItemsPerPage()`, `setPageNum()`, `setNumItemsTotal()` and `SqlPaginator::clearResults()`, invalidating only when the input actually changed; verify the `paginator-cache-invalidation` scenarios pass in `tests/PaginatorBaseTest.php`, inverting the characterisation test from 1.3
- [ ] 5.2 Add a test that reuses one `SqlPaginator` across two queries returning different row counts and asserts the item count, total count and page count all describe the second query; verify with `vendor/bin/phpunit --no-coverage --filter Paginator`
- [ ] 5.3 Confirm a repeated `setMaxItemsPerPage()` with an unchanged value does not trigger a second `COUNT(*)` query; verify with a test counting prepared statements on the mock PDO used by `tests/RepositoryPdoTest.php`

## 6. Search-type constant relocation

- [ ] 6.1 Define the canonical `STARTS`, `ENDS` and `CONTAINS` values on `Sql`, redefine the `Repository` constants to reference them, and update `Sql::likeWildcards()` to use its own constants so `Sql` no longer references `Repository`; verify `grep -n 'Repository::' src/Sql.php` returns nothing and the full suite passes
- [ ] 6.2 Confirm `Repository::CONTAINS`, `Repository::STARTS` and `Repository::ENDS` still resolve to the same string values as before, since consumers write `BaseRepository::CONTAINS`; verify with an assertion on each constant's value in `tests/SqlTest.php`

## 7. Documentation and final verification

- [ ] 7.1 Check `README.md` and `examples/` for statements contradicted by the new null, empty-array and identifier-validation semantics, and update any that are now wrong; verify by grepping the docs for the affected constants and paginator methods and reading each hit
- [ ] 7.2 Run `vendor/bin/phpcs` and confirm zero violations across `src`, `tests` and `examples`
- [ ] 7.3 Run `vendor/bin/phpunit --no-coverage` and confirm the full suite passes with every characterisation test from group 1 now asserting the corrected behaviour
