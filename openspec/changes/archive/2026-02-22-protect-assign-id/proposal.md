## Why

The `assignId()` method introduced in the `implicit-id-field` change is public and on `ItemInterface`, meaning any consumer can call it to bypass the `setId()` guard. An AI agent or developer who discovers that `setId()` throws could simply use `assignId()` instead, defeating the purpose of making the id auto-managed.

## What Changes

- **BREAKING**: Remove `assignId()` from `ItemInterface` — it is not part of the consumer contract.
- Add `private bool $persisted = false` property to `Item` — tracks whether the item has been through a persistence operation.
- `clearOriginalValues()` sets `$persisted = true` — acts as the gate for `assignId()`.
- `assignId()` guards with `$this->persisted && $this->isTemp()` — only succeeds after a persistence operation on a temp item.
- Reorder `Repository::insertItem()` to call `clearOriginalValues()` before `assignId()`.

## Capabilities

### New Capabilities

_(none)_

### Modified Capabilities

- `implicit-id-field`: The `assignId()` requirement changes — it gains runtime guards (`persisted` flag + `isTemp()` check) and is removed from `ItemInterface`.

## Impact

- **src/Item.php**: Add `$persisted` property, update `clearOriginalValues()` to set it, add guards to `assignId()`.
- **src/ItemInterface.php**: Remove `assignId()` method.
- **src/Repository.php**: Reorder `insertItem()` — `clearOriginalValues()` before `assignId()`.
- **tests/ItemTest.php**: Update `assignId()` tests to call `clearOriginalValues()` first, add tests for guard behaviour.
- **tests/RepositoryDbTest.php**: Verify INSERT flow still works with new call order.
