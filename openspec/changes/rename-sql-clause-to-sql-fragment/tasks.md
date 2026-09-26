## 1. Rename the class

- [x] 1.1 Move `src/Sql/SqlClause.php` to `src/Sql/SqlFragment.php` with `git mv`, rename the class to `SqlFragment`, and correct its docblocks so the file header and class description both describe a SQL fragment of any scale with the parameters it binds, and the `$sql` constructor parameter no longer claims to exclude an introducing keyword; verify by reading the file and confirming no remaining use of the word "clause" describes the value itself
- [x] 1.2 Update every reference in `src/Sql/SqlQuery.php` and `src/Repository.php`, including imports, signatures, union types in `with()` and `derive()`, the `render()` return type, and docblocks, leaving "clause" wherever it names a `SqlQuery` slot or a repository hook; verify `grep -rn 'SqlClause' src` returns nothing

## 2. Tests

- [x] 2.1 Move `tests/Sql/SqlClauseTest.php` to `tests/Sql/SqlFragmentTest.php` with `git mv` and rename the test class; verify with `vendor/bin/phpunit --no-coverage --filter SqlFragmentTest`
- [x] 2.2 Update the references in `tests/Sql/SqlQueryTest.php`, `tests/Paginator/SqlPaginatorQueryTest.php` and `tests/RepositoryHandWrittenQueryTest.php`; verify `grep -rn 'SqlClause' tests` returns nothing and `vendor/bin/phpunit --no-coverage` passes with the same test count as before the change (419 tests, 1278 assertions when this change was planned)

## 3. Documentation

- [ ] 3.1 Update `README.md` and `examples/04-joins.php` to use `SqlFragment`, adjusting any surrounding prose that describes the value as a clause; verify `grep -rn 'SqlClause' README.md examples docs` returns nothing and `php examples/04-joins.php` runs without error if the example is runnable standalone

## 4. Final verification

- [ ] 4.1 Confirm the only remaining occurrences of `SqlClause` in the repository are in `openspec/changes/archive/`, which is left untouched as an accurate record; verify with `grep -rln 'SqlClause' . --exclude-dir=vendor --exclude-dir=tools`
- [ ] 4.2 Run `vendor/bin/phpcs` and confirm zero violations
- [ ] 4.3 Run `vendor/bin/phpunit --no-coverage` and confirm the full suite passes
