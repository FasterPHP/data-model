## 1. Core Item changes

- [x] 1.1 Add `public const ID_TYPE = Field\Integer::class` to `Item` base class
- [x] 1.2 Update `getField()` to recognise `ID_INTERNAL` without it being in any FIELDS constant — use `ID_TYPE` as the field class, `$isReadonly = false`
- [x] 1.3 Add guard in `getField()`: if `static::FIELDS` contains a key matching `ID_INTERNAL`, throw `Exception` with migration message ("Do not declare 'id' in FIELDS ... use ID_TYPE")
- [x] 1.4 Add `setId()` guard in `__call()`: if the setter targets `ID_INTERNAL`, throw `Exception("Cannot set id directly; the id field is managed automatically")`
- [x] 1.5 Add `public function assignId(mixed $id): void` that calls `$this->getField(static::ID_INTERNAL)->setValue($id)`
- [x] 1.6 Update `jsonSerialize()` to return `[static::ID_INTERNAL => $this->getId()] + $this->getValues()`
- [x] 1.7 Update `__serialize()` to return `[static::ID_INTERNAL => $this->getId()] + $this->getValues()`
- [x] 1.8 Update `__toString()` to use the updated serialisation (includes id)

## 2. Repository changes

- [x] 2.1 Update `insertItem()` to call `$item->assignId($newId)` instead of `$item->setId($newId)`
- [x] 2.2 Update `getFieldList()` to prepend the id column (`ID_FIELD AS ID_INTERNAL`) explicitly, since id is no longer in `FIELDS`

## 3. Interface changes

- [x] 3.1 Add `assignId(mixed $id): void` to `ItemInterface`

## 4. Update test model Item classes — remove `'id'` from FIELDS

- [x] 4.1 `tests/TestModel/ValidItem.php`
- [x] 4.2 `tests/TestModel/ReadonlyItem.php`
- [x] 4.3 `tests/TestModel/ExternalItem.php`
- [x] 4.4 `tests/TestModel/AggregateItem.php`
- [x] 4.5 `tests/TestModel/NoValidatorsItem.php`
- [x] 4.6 `tests/TestModel/SkipIfEmptyItem.php`
- [x] 4.7 `tests/TestModel/CallbackValidatorItem.php`
- [x] 4.8 `tests/CustomValidatorItem.php`
- [x] 4.9 `tests/TestValidatorItem.php`

## 5. Update existing tests

- [x] 5.1 Update `ItemTest.php` — change `setId()` tests to expect exceptions, update tests that reference id in `getValues()` output, add tests for `assignId()`
- [x] 5.2 Update `RepositoryDbTest.php` — verify auto-increment flow still works with `assignId()`
- [x] 5.3 Update any other tests that call `setId()` or assert id in `getValues()` output

## 6. Add new tests

- [x] 6.1 Test implicit id field creation (getId on item without id in FIELDS)
- [x] 6.2 Test ID_TYPE override (custom field type for id)
- [x] 6.3 Test setId() throws with clear message
- [x] 6.4 Test assignId() sets value and affects isTemp()
- [x] 6.5 Test assignId() does not affect isDirty()
- [x] 6.6 Test error when id declared in FIELDS (migration guard)
- [x] 6.7 Test serialisation methods include id (jsonSerialize, __serialize, __toString)
- [x] 6.8 Test getValues() and getSqlValues() exclude id

## 7. Update examples — remove `'id'` from FIELDS

- [x] 7.1 `examples/01-basic-usage.php`
- [x] 7.2 `examples/02-pagination.php`
- [x] 7.3 `examples/03-validation.php`
- [x] 7.4 `examples/04-joins.php` (two Item classes)
- [x] 7.5 `examples/05-batch-operations.php`
- [x] 7.6 `examples/06-symfony-validation.php`
- [x] 7.7 `examples/07-laravel-validation.php`
- [x] 7.8 `examples/08-custom-validators.php` (two Item classes)

## 8. Update documentation

- [x] 8.1 Update `README.md` — remove `'id'` from FIELDS in all code examples, document `ID_TYPE`, document that id is implicit and auto-managed
- [x] 8.2 Run `phpcs` and `phpunit` to verify all changes pass
