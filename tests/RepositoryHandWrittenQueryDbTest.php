<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\Sql\SqlFragment;
use FasterPhp\DataModel\Sql\SqlQuery;
use FasterPhp\DataModel\TestModel\HandWrittenRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests against an in-memory SQLite database showing that a repository returning a
 * hand-written joined query returns the rows the caller asked for.
 */
class RepositoryHandWrittenQueryDbTest extends TestCase
{
    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (userId INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $pdo->exec('CREATE TABLE accounts (accountId INTEGER PRIMARY KEY, userId INTEGER NOT NULL, '
            . 'name TEXT NOT NULL, status TEXT NOT NULL)');
        $pdo->exec("INSERT INTO users (userId, name) VALUES (1, 'Alice'), (2, 'Bob'), (3, 'Carol'), (4, 'Dave')");
        $pdo->exec("INSERT INTO accounts (accountId, userId, name, status) VALUES "
            . "(10, 1, 'Acme', 'active'), (20, 2, 'Globex', 'active'), "
            . "(30, 3, 'Initech', 'active'), (40, 4, 'Hooli', 'closed')");
        return $pdo;
    }

    private function createRepository(): HandWrittenRepository
    {
        return (new HandWrittenRepository($this->createPdo(), new Sort('users.userId')))
            ->setMaxItemsPerPage(null)
            ->setHandWrittenQuery(new SqlQuery(
                new SqlFragment('`users`.`userId` AS `id`, `users`.`name`, `accounts`.`name` AS accountName'),
                new SqlFragment('`users` JOIN `accounts` ON `accounts`.`userId` = `users`.`userId`'),
                new SqlFragment('`accounts`.`status` = :q_status', [':q_status' => 'active']),
            ));
    }

    /**
     * An identity lookup of a row that is not the query's first returns that row.
     */
    public function testIdentityLookupReturnsTheRequestedRow(): void
    {
        $item = $this->createRepository()->getItemWithId(2);

        $this->assertNotNull($item);
        $this->assertSame(2, $item->getId());
        $this->assertSame('Bob', $item->getName());
        $this->assertSame('Globex', $item->getAccountName());
    }

    /**
     * An identity lookup of a row the query's own condition excludes returns nothing.
     */
    public function testIdentityLookupOfExcludedRowReturnsNothing(): void
    {
        $this->assertNull($this->createRepository()->getItemWithId(4));
    }

    /**
     * A filtered Set contains only the matching rows the query itself selects.
     */
    public function testFilteredSetReturnsOnlyMatchingRows(): void
    {
        $set = $this->createRepository()->getSetWithParams(
            ['name' => ['Bob', 'Carol', 'Dave']],
        );

        $names = [];
        foreach ($set as $item) {
            $names[] = $item->getName();
        }
        $this->assertSame(['Bob', 'Carol'], $names);
    }
}
