## Context

`Paginator\Base` has two layers for `maxItemsPerPage` and `maxPageLinks`:
1. A static default (`$defaultMaxItemsPerPage`) set once at application boot
2. An instance value (`$maxItemsPerPage`) set per-query via `setMaxItemsPerPage()`

The getter uses `isset()` to decide which to return. Since `isset(null)` is `false`, calling `setMaxItemsPerPage(null)` looks identical to "never called" — the static default always takes precedence.

## Goals / Non-Goals

**Goals:**
- Allow `setMaxItemsPerPage(null)` to override the static default, returning `null` (unlimited).
- Apply the same fix to `maxPageLinks` for consistency.

**Non-Goals:**
- Changing the static default mechanism itself.
- Modifying the `setSort()` or `setPageNum()` `unset()` patterns that reset cached items.

## Decisions

**Use a boolean flag (`$maxItemsPerPageSet`) to track explicit calls**

When `setMaxItemsPerPage()` is called (with any value including `null`), set `$this->maxItemsPerPageSet = true`. The getter checks this flag instead of `isset()`.

Alternative considered: using a sentinel value (e.g. `-1` meaning "use default"). Rejected because it abuses the type system and requires documenting a magic value.

Alternative considered: removing the `?` from the property and using `unset()` to represent "not set". Rejected because `setMaxItemsPerPage()` already calls `unset($this->items)` for cache invalidation, and mixing `unset` semantics with value storage is fragile.

## Risks / Trade-offs

- **[Minimal BC risk]** Any code that calls `setMaxItemsPerPage(null)` expecting it to be a no-op (falling through to the default) will now get unlimited results. This is the correct/expected behaviour per the method signature, so this is a bug fix, not a behaviour change. → No mitigation needed.
