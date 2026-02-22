<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\Paginator;

/**
 * Minimal concrete subclass of Base for testing.
 */
class TestPaginator extends Base
{
    public function getItems(): array
    {
        return $this->items ?? [];
    }
}
