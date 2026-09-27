## 1. Baseline characterisation

- [x] 1.1 Add tests to `tests/AggregateFieldsRepositoryTest.php`, beside `testRegularFieldIsTableQualified`, recording current behaviour: `getComparison()` with the Item's `ID_FIELD` key returns a bare identifier, and with the key `id` returns `` `id` ``, each with its placeholder; verify on the unchanged code with `vendor/bin/phpunit --no-coverage --filter AggregateFieldsRepository`, and that the full suite reports 467 tests and 1414 assertions plus the new ones

## 2. Qualify the ID column

- [x] 2.1 In `Repository::getComparison()`, resolve the key `ID_INTERNAL` to `ID_FIELD` when `ID_FIELD` is non-empty, and treat a key equal to `ID_FIELD` as belonging to the base table, prefixing it with `getTableName()`; keep the placeholder derived from the key as given; update the inline comment describing which keys are qualified; verify the characterisation tests from 1.1, inverted, now assert `` `<table>`.`<ID_FIELD>` `` with the unchanged placeholders
- [x] 2.2 Add tests for the remaining scenarios of the modified requirement: an `id => [1, 2, 3]` list renders an IN on the qualified ID column; an Item whose `ID_FIELD` is `id` renders `` `<table>`.`id` ``; an Item with no `ID_FIELD` keeps the bare `id`; aggregate, external, dotted and unrecognised keys are unchanged; verify with `vendor/bin/phpunit --no-coverage --filter AggregateFieldsRepository`
- [ ] 2.3 Add a test in which a repository whose from clause joins a table sharing its ID column's name (for example the `FromOverrideRepository` fixture, which joins `accounts` on `userId`) retrieves a Set filtered on the bare ID key, asserting through a recording PDO that the executed WHERE references `` `users`.`userId` `` and binds `:userId`; verify with `vendor/bin/phpunit --no-coverage --filter QueryHook` or the file the test is added to
- [ ] 2.4 Update the filtering section of `README.md`: state that filters on declared fields and on the ID column (by its own name or as `id`) are qualified with the table name, while external, aggregate and dotted keys are used as given, and confirm the existing `'id' => [1, 2, 3, 4, 5]` example is now correct as written; verify by reading the section against the modified spec

## 3. Final verification

- [ ] 3.1 Run `vendor/bin/phpcs` and confirm zero violations
- [ ] 3.2 Run `vendor/bin/phpunit --no-coverage` and confirm the full suite passes with more than 467 tests and 1414 assertions, and that `tests/RepositorySelectSqlTest.php` baselines are unchanged from `main`
- [ ] 3.3 Run every script in `examples/` and confirm each runs to completion
- [ ] 3.4 Confirm nothing added by this change names a private consumer, application or client, or a local machine path, by searching the change's diff and OpenSpec artifacts; verify the search finds nothing
