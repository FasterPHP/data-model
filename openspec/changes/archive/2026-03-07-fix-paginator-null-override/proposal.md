## Why

`Paginator\Base::setMaxItemsPerPage(null)` is intended to remove pagination (no `LIMIT` clause), but it doesn't work when a static default has been set via `setDefaultMaxItemsPerPage()`. The getter uses `isset($this->maxItemsPerPage)`, which returns `false` for `null`, so the static default always wins. This makes it impossible for a caller to opt out of pagination on a specific query when an application-wide default is configured. The same issue affects `maxPageLinks`.

## What Changes

- `Paginator\Base::getMaxItemsPerPage()` will respect an explicitly-set `null` value, only falling back to the static default when the instance value has never been set.
- `Paginator\Base::getMaxPageLinks()` will receive the same fix for consistency.

## Capabilities

### New Capabilities

- `paginator-instance-override`: Instance-level pagination settings override static defaults, including explicit `null` (unlimited).

### Modified Capabilities

None.

## Impact

- **Code**: `src/Paginator/Base.php` — `getMaxItemsPerPage()` and `getMaxPageLinks()` need to distinguish "never set" from "explicitly set to null".
- **Consumers**: Any application using `setDefaultMaxItemsPerPage()` and then calling `setMaxItemsPerPage(null)` on an instance will now correctly get unlimited results. This is the intended/expected behaviour, so no breakage is expected.
- **Risk**: Low — this fixes the method to behave as its signature (`?int`) already promises.
