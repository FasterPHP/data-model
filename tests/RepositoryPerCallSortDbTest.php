<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\TestModel\ValidRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests against an in-memory SQLite database showing that a single-item lookup given a
 * sort of its own returns the first row under that sort and leaves the repository's order alone.
 */
class RepositoryPerCallSortDbTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (userId INTEGER PRIMARY KEY, name TEXT NOT NULL, age INTEGER NOT NULL, '
            . 'height REAL NOT NULL, handsome TEXT NOT NULL)');
        $pdo->exec("INSERT INTO users (userId, name, age, height, handsome) VALUES "
            . "(1, 'Carol', 30, 5.5, 'y'), (2, 'Alice', 25, 5.9, 'y'), "
            . "(3, 'Bob', 40, 6.1, 'n'), (4, 'Dave', 35, 5.7, 'y')");
        return $pdo;
    }

    /**
     * A lookup sorted by id descending returns the latest matching row, and a Set retrieved
     * afterwards is still in the repository's own order.
     */
    public function testPerCallSortReturnsLatestRowAndLeavesSetOrderAlone(): void
    {
        $repo = new ValidRepository($this->createPdo(), new Sort('users.name'));

        $item = $repo->getItemWithParams(['handsome' => 'y'], sort: new Sort('id', Sort::DESCENDING));

        $this->assertNotNull($item);
        $this->assertSame(4, $item->getId());
        $this->assertSame('Dave', $item->getName());

        $names = [];
        foreach ($repo->getSetWithParams(['handsome' => 'y']) as $setItem) {
            $names[] = $setItem->getName();
        }
        $this->assertSame(['Alice', 'Carol', 'Dave'], $names);
    }
}
