<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use PHPUnit\Framework\TestCase;
use PDO;
use PDOException;
use FasterPhp\DataModel\TestModel\ValidItem;
use FasterPhp\DataModel\TestModel\ValidRepository;
use FasterPhp\DataModel\TestModel\ValidSet;

/**
 * Transaction ownership and Item state across commit and rollback, against in-memory SQLite.
 */
class RepositoryTransactionTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE users (
                userId INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                age INTEGER,
                height REAL,
                handsome TEXT
            )'
        );
    }

    /**
     * Characterisation: a save with the flag set inside a caller's transaction currently throws.
     */
    public function testSaveItemInsideCallerTransactionCurrentlyThrows(): void
    {
        $repo = new ValidRepository($this->pdo);
        $this->pdo->beginTransaction();

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('already an active transaction');
        $repo->saveItem($this->newUser('Alice'), true);
    }

    /**
     * Characterisation: a rolled-back Set currently leaves its earlier Items claiming to be saved.
     */
    public function testRolledBackSaveSetCurrentlyLeavesEarlierItemsMarkedPersisted(): void
    {
        $repo = new ValidRepository($this->pdo);
        $items = [$this->newUser('Alice'), $this->newUser('Bob'), $this->newUser('Alice')];
        $set = $this->newSet($items);

        try {
            $repo->saveSet($set, true);
            $this->fail('Expected the duplicate name to violate the UNIQUE constraint');
        } catch (PDOException) {
            // Expected.
        }

        $this->assertSame(0, $this->countRows('users'));
        $this->assertFalse($items[0]->isTemp());
        $this->assertNotNull($items[0]->getId());
        $this->assertFalse($items[1]->isTemp());
        $this->assertNotNull($items[1]->getId());
    }

    private function newUser(string $name): ValidItem
    {
        $item = new ValidItem();
        $item->setName($name);
        $item->setAge(30);
        $item->setHeight(5.9);
        $item->setHandsome(true);
        return $item;
    }

    /**
     * @param list<ValidItem> $items
     */
    private function newSet(array $items): ValidSet
    {
        $set = new ValidSet();
        foreach ($items as $item) {
            $set->addItem($item);
        }
        return $set;
    }

    private function countRows(string $table): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
}
