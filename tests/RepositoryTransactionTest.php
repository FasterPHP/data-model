<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PDO;
use PDOException;
use PDOStatement;
use FasterPhp\DataModel\TestModel\ReadonlyItem;
use FasterPhp\DataModel\TestModel\ReadonlyRepository;
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
     * A save with the flag set inside a caller's transaction joins it rather than throwing.
     */
    public function testSaveItemInsideCallerTransactionJoinsIt(): void
    {
        $repo = new ValidRepository($this->pdo);
        $this->pdo->beginTransaction();

        $repo->saveItem($this->newUser('Alice'), true);

        $this->assertTrue($this->pdo->inTransaction());
        $this->pdo->rollBack();
        $this->assertSame(0, $this->countRows('users'));
    }

    /**
     * A Set saved with the flag set inside a caller's transaction joins it rather than throwing.
     */
    public function testSaveSetInsideCallerTransactionJoinsIt(): void
    {
        $repo = new ValidRepository($this->pdo);
        $this->pdo->beginTransaction();

        $repo->saveSet($this->newSet([$this->newUser('Alice'), $this->newUser('Bob')]), true);

        $this->assertTrue($this->pdo->inTransaction());
        $this->pdo->rollBack();
        $this->assertSame(0, $this->countRows('users'));
    }

    /**
     * Joining a caller's transaction never begins or commits one.
     */
    #[DataProvider('saveMethodProvider')]
    public function testJoinedSaveNeitherBeginsNorCommits(string $method): void
    {
        $pdo = $this->createTransactionMock(inTransaction: true);
        $pdo->expects($this->never())->method('beginTransaction');
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->never())->method('rollBack');

        $this->save(new ValidRepository($pdo), $method, $this->newUser('Alice'), true);
    }

    /**
     * A failure inside a caller's transaction is rethrown as-is, with no rollback attempted.
     */
    #[DataProvider('saveMethodProvider')]
    public function testJoinedFailureRethrowsWithoutRollBack(string $method): void
    {
        $original = new PDOException('DB error');
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willThrowException($original);

        $pdo = $this->createTransactionMock(inTransaction: true, stmt: $stmt);
        $pdo->expects($this->never())->method('rollBack');

        try {
            $this->save(new ValidRepository($pdo), $method, $this->newUser('Alice'), true);
            $this->fail('Expected the statement failure to propagate');
        } catch (PDOException $e) {
            $this->assertSame($original, $e);
        }
    }

    /**
     * A failure inside a caller's transaction leaves that transaction active for its owner.
     */
    #[DataProvider('saveMethodProvider')]
    public function testJoinedFailureLeavesCallerTransactionActive(string $method): void
    {
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
        $repo = new ValidRepository($this->pdo);
        $this->pdo->beginTransaction();
        $repo->saveItem($this->newUser('Bob'), true);

        try {
            $this->save($repo, $method, $this->newUser('Alice'), true);
            $this->fail('Expected the duplicate name to violate the UNIQUE constraint');
        } catch (PDOException $e) {
            $this->assertStringContainsString('UNIQUE', $e->getMessage());
        }

        $this->assertTrue($this->pdo->inTransaction());
        $this->pdo->commit();
        $this->assertSame(2, $this->countRows('users'));
    }

    /**
     * Saves through two repositories in one caller transaction are durable together.
     */
    public function testRepositoriesSharingCallerTransactionCommitTogether(): void
    {
        $this->saveThroughTwoRepositoriesInCallerTransaction();
        $this->pdo->commit();

        $this->assertSame(1, $this->countRows('users'));
        $this->assertSame(1, $this->countRows('readonly_users'));
    }

    /**
     * Saves through two repositories in one caller transaction are undone together.
     */
    public function testRepositoriesSharingCallerTransactionRollBackTogether(): void
    {
        $this->saveThroughTwoRepositoriesInCallerTransaction();
        $this->pdo->rollBack();

        $this->assertSame(0, $this->countRows('users'));
        $this->assertSame(0, $this->countRows('readonly_users'));
    }

    /**
     * With no transaction active, the flag begins one and commits it once the save succeeds.
     */
    #[DataProvider('saveMethodProvider')]
    public function testOwnedTransactionBeginsAndCommits(string $method): void
    {
        $this->save(new ValidRepository($this->pdo), $method, $this->newUser('Alice'), true);

        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame(1, $this->countRows('users'));
    }

    /**
     * An owned transaction rolls back on failure and the original exception is rethrown.
     */
    #[DataProvider('saveMethodProvider')]
    public function testOwnedTransactionRollsBackAndRethrows(string $method): void
    {
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
        $repo = new ValidRepository($this->pdo);

        try {
            if ($method === 'saveSet') {
                $repo->saveSet($this->newSet([$this->newUser('Bob'), $this->newUser('Alice')]), true);
            } else {
                $repo->saveItem($this->newUser('Alice'), true);
            }
            $this->fail('Expected the duplicate name to violate the UNIQUE constraint');
        } catch (PDOException $e) {
            $this->assertStringContainsString('UNIQUE', $e->getMessage());
        }

        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame(1, $this->countRows('users'));
    }

    /**
     * Without the flag, a save neither begins, commits nor rolls back a transaction.
     */
    #[DataProvider('saveMethodProvider')]
    public function testUnflaggedSaveLeavesTransactionsAlone(string $method): void
    {
        $pdo = $this->createTransactionMock(inTransaction: false);
        $pdo->expects($this->never())->method('beginTransaction');
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->never())->method('rollBack');

        $this->save(new ValidRepository($pdo), $method, $this->newUser('Alice'), false);
    }

    /**
     * Without the flag, a failing save does not roll back a transaction the caller holds.
     */
    public function testUnflaggedFailureLeavesCallerTransactionActive(): void
    {
        $this->pdo->exec("INSERT INTO users (name) VALUES ('Alice')");
        $repo = new ValidRepository($this->pdo);
        $this->pdo->beginTransaction();
        $repo->saveItem($this->newUser('Bob'));

        try {
            $repo->saveItem($this->newUser('Alice'));
            $this->fail('Expected the duplicate name to violate the UNIQUE constraint');
        } catch (PDOException) {
            // Expected.
        }

        $this->assertTrue($this->pdo->inTransaction());
        $this->pdo->commit();
        $this->assertSame(2, $this->countRows('users'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function saveMethodProvider(): array
    {
        return [
            'saveItem' => ['saveItem'],
            'saveSet'  => ['saveSet'],
        ];
    }

    /**
     * A rolled-back Set leaves the Items written before the failure temporary, with no id.
     */
    public function testRolledBackSaveSetLeavesEarlierItemsTemporary(): void
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
        $this->assertTrue($items[0]->isTemp());
        $this->assertNull($items[0]->getId());
        $this->assertTrue($items[1]->isTemp());
        $this->assertNull($items[1]->getId());
    }

    /**
     * Begin a transaction and save one new Item through each of two repositories with the flag set.
     */
    private function saveThroughTwoRepositoriesInCallerTransaction(): void
    {
        $this->pdo->exec(
            'CREATE TABLE readonly_users (
                userId INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                email TEXT,
                createdAt TEXT,
                updatedAt TEXT
            )'
        );

        $account = new ReadonlyItem();
        $account->setName('Alice');
        $account->setEmail('alice@example.com');

        $this->pdo->beginTransaction();
        (new ValidRepository($this->pdo))->saveItem($this->newUser('Alice'), true);
        (new ReadonlyRepository($this->pdo))->saveItem($account, true);

        $this->assertTrue($this->pdo->inTransaction());
    }

    /**
     * Save one Item through either save method.
     */
    private function save(ValidRepository $repo, string $method, ValidItem $item, bool $useTransaction): void
    {
        if ($method === 'saveSet') {
            $repo->saveSet($this->newSet([$item]), $useTransaction);
        } else {
            $repo->saveItem($item, $useTransaction);
        }
    }

    /**
     * A partial PDO mock reporting the given transaction state, whose statements succeed unless one is given.
     */
    private function createTransactionMock(bool $inTransaction, ?PDOStatement $stmt = null): PDO&MockObject
    {
        if ($stmt === null) {
            $stmt = $this->createMock(PDOStatement::class);
            $stmt->method('execute')->willReturn(true);
        }

        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare', 'beginTransaction', 'commit', 'rollBack', 'inTransaction', 'lastInsertId'])
            ->getMock();
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('inTransaction')->willReturn($inTransaction);
        $pdo->method('lastInsertId')->willReturn('42');

        return $pdo;
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
