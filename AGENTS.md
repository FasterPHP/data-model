# AGENTS.md

## Quick reference
Install: `composer install`
Test: `phpunit` (with coverage report in `build` dir) or `phpunit --no-coverage` without coverage

## Documentation
[Project overview](README.md)
[Coding standards](docs/standards/coding-guidelines.md)
[Testing guidelines](docs/standards/testing-guidelines.md)
[Architecture decisions](docs/adrs/)

## Workflow
This project uses OpenSpec for spec-driven development.
See [openspec/](openspec/) for workflow instructions.

Configuration: [openspec/config.yaml](openspec/config.yaml)

## Documentation upkeep
When modifying public API in `src/` (adding, removing, or renaming classes, constants, methods, or method signatures), check whether `README.md` or files in `examples/` reference the changed symbol. If so, update the documentation and examples in the same change.

## Test upkeep
When modifying or adding code in `src/`, update or add corresponding tests in `tests/` in the same change. Ensure existing tests still pass and new behaviour is covered.

## Constraints
- PHP 8.2+ with `declare(strict_types=1)` in every PHP file
- PSR-12 coding standard — run `vendor/bin/phpcs` before committing
- All tests must pass: `vendor/bin/phpunit --no-coverage`
- No hidden global state — PDO is always injected, never accessed via singletons or globals
- Do not add dependencies without discussion — this is a lightweight library by design
- Maintain the `{Prefix}Item` / `{Prefix}Set` / `{Prefix}Repository` naming convention
- Validation must remain opt-in via traits — Items without traits must have zero validation overhead
