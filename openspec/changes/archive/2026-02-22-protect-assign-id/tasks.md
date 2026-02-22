## 1. Item changes

- [x] 1.1 Add `private bool $persisted = false` property to `Item`
- [x] 1.2 Update `clearOriginalValues()` to set `$this->persisted = true`
- [x] 1.3 Add guards to `assignId()`: throw if `!$this->persisted || !$this->isTemp()`

## 2. Interface changes

- [x] 2.1 Remove `assignId()` from `ItemInterface`

## 3. Repository changes

- [x] 3.1 Reorder `insertItem()`: call `clearOriginalValues()` before `assignId()`

## 4. Update existing tests

- [x] 4.1 Update `ItemTest` `assignId` tests — call `clearOriginalValues()` before `assignId()` where the call should succeed
- [x] 4.2 Add test: `assignId()` throws on a new item without `clearOriginalValues()`
- [x] 4.3 Add test: `assignId()` throws on a loaded item (with id)
- [x] 4.4 Add test: `assignId()` throws on a loaded item even after `clearOriginalValues()`
- [x] 4.5 Verify `RepositoryDbTest` INSERT flow still works with new call order

## 5. Verify

- [x] 5.1 Run `phpcs` and `phpunit` — all checks pass
