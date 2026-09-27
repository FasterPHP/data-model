## 1. Fix Paginator\Base

- [x] 1.1 Add `private bool $maxItemsPerPageSet = false` property and set it to `true` in `setMaxItemsPerPage()`. Update `getMaxItemsPerPage()` to check `$this->maxItemsPerPageSet` instead of `isset($this->maxItemsPerPage)`.
- [x] 1.2 Add `private bool $maxPageLinksSet = false` property and set it to `true` in `setMaxPageLink()`. Update `getMaxPageLinks()` to check `$this->maxPageLinksSet` instead of `isset($this->maxPageLinks)`.

## 2. Tests

- [x] 2.1 Add test: `setMaxItemsPerPage(null)` returns `null` when a static default is set
- [x] 2.2 Add test: static default is used when `setMaxItemsPerPage()` was never called
- [x] 2.3 Add test: `setMaxPageLink(null)` returns `null` when a static default is set
