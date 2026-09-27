<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\Sql\SqlFragment;
use FasterPhp\DataModel\Sql\SqlQuery;
use FasterPhp\DataModel\TestModel\HandWrittenItem;
use FasterPhp\DataModel\TestModel\HandWrittenRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests for returning a hand-written query from the repository query hook, as a supported
 * substitute for the default composition of the clause hooks.
 */
class RepositoryHandWrittenQueryTest extends TestCase
{
    /** @var list<array{sql: string, params: array<string, mixed>|null}> */
    private array $executions = [];

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /**
     * Build a PDO recording every prepared statement and the parameters it was executed with.
     */
    private function createRecordingPdo(): PDO
    {
        $stmt = $this->getMockBuilder(PDOStatement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['execute', 'fetchAll'])
            ->getMock();
        $stmt->method('execute')
            ->willReturnCallback(function (?array $params = null): bool {
                $this->executions[array_key_last($this->executions)]['params'] = $params;
                return true;
            });
        $stmt->method('fetchAll')->willReturnCallback(fn(): array => $this->rows);

        $pdo = $this->getMockBuilder(PDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock();
        $pdo->method('prepare')
            ->willReturnCallback(function (string $sql) use ($stmt) {
                $this->executions[] = ['sql' => $sql, 'params' => null];
                return $stmt;
            });

        return $pdo;
    }

    private function createQuery(): SqlQuery
    {
        return new SqlQuery(
            new SqlFragment('`users`.`userId` AS `id`, `users`.`name`, `accounts`.`name` AS accountName'),
            new SqlFragment('`users` JOIN `accounts` ON `accounts`.`userId` = `users`.`userId`'),
            new SqlFragment('`accounts`.`status` = :status', [':status' => 'active']),
        );
    }

    /**
     * The hand-written query is executed as given, and the clause hooks do not contribute.
     */
    public function testHandWrittenQueryIsExecutedAsGiven(): void
    {
        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setHandWrittenQuery($this->createQuery());

        $repo->getSetOfAll();

        $this->assertCount(1, $this->executions);
        $this->assertSame($this->createQuery()->render()->getSql(), $this->executions[0]['sql']);
        $this->assertSame(0, $repo->selectClauseCalls);
        $this->assertSame(0, $repo->fromClauseCalls);
    }

    /**
     * A hand-written query still receives the repository's sorting and pagination.
     */
    public function testHandWrittenQueryIsSortedAndPaginated(): void
    {
        $repo = (new HandWrittenRepository($this->createRecordingPdo(), new Sort('users.name')))
            ->setMaxItemsPerPage(5)
            ->setHandWrittenQuery($this->createQuery());

        $repo->getSetOfAll();

        $this->assertStringEndsWith(
            'ORDER BY `users`.`name` ASC LIMIT 5',
            $this->executions[0]['sql']
        );
    }

    /**
     * Its rows are still returned as Items of the repository's Item class.
     */
    public function testHandWrittenQueryResultsAreItems(): void
    {
        $this->rows = [
            ['id' => 1, 'name' => 'Alice', 'accountName' => 'Acme'],
            ['id' => 2, 'name' => 'Bob', 'accountName' => 'Globex'],
        ];

        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setHandWrittenQuery($this->createQuery());

        $set = $repo->getSetOfAll();

        $this->assertCount(2, $set);
        foreach ($set as $item) {
            $this->assertInstanceOf(HandWrittenItem::class, $item);
        }
        $this->assertSame('Alice', $set[0]->getName());
        $this->assertSame('Acme', $set[0]->getAccountName());
    }

    /**
     * A single Item retrieved from a hand-written query is built the same way.
     */
    public function testHandWrittenQueryItemRetrieval(): void
    {
        $this->rows = [['id' => 1, 'name' => 'Alice', 'accountName' => 'Acme']];

        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setHandWrittenQuery($this->createQuery());

        $item = $repo->getItemWithParams([]);

        $this->assertInstanceOf(HandWrittenItem::class, $item);
        $this->assertSame('Alice', $item->getName());
        $this->assertStringEndsWith('LIMIT 1', $this->executions[0]['sql']);
    }

    /**
     * Characterisation: an identity lookup against a hand-written query that ignores the hook's
     * filters currently executes with no condition on the id column.
     */
    public function testIdentityLookupOnHandWrittenQueryIgnoresTheId(): void
    {
        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setHandWrittenQuery($this->createQuery());

        $repo->getItemWithId(2);

        $this->assertStringNotContainsString('`users`.`userId` =', $this->executions[0]['sql']);
        $this->assertSame([':status' => 'active'], $this->executions[0]['params']);
    }

    /**
     * Characterisation: a filter on a declared field is currently absent from the SQL executed
     * for a hand-written query that ignores the hook's filters.
     */
    public function testFilterOnHandWrittenQueryIsIgnored(): void
    {
        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setHandWrittenQuery($this->createQuery());

        $repo->getSetWithParams(['name' => 'Alice']);

        $this->assertStringNotContainsString('`users`.`name` =', $this->executions[0]['sql']);
        $this->assertSame([':status' => 'active'], $this->executions[0]['params']);
    }

    /**
     * The parameters bound by a hand-written query's clauses are applied on execution.
     */
    public function testHandWrittenQueryBindsItsOwnParameters(): void
    {
        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setHandWrittenQuery(
                $this->createQuery()->with(having: new SqlFragment('COUNT(*) > :minimum', [':minimum' => 2]))
            );

        $repo->getSetOfAll();

        $this->assertSame([':status' => 'active', ':minimum' => 2], $this->executions[0]['params']);
    }

    /**
     * A hand-written query whose clauses collide on a parameter name throws rather than
     * discarding a binding.
     */
    public function testHandWrittenQueryWithCollidingParametersThrows(): void
    {
        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setHandWrittenQuery(new SqlQuery(
                new SqlFragment('`users`.`userId` AS `id`, `users`.`name`'),
                new SqlFragment('`users`'),
                new SqlFragment('`users`.`name` = :value', [':value' => 'Alice']),
                null,
                new SqlFragment('COUNT(*) = :value', [':value' => 3]),
            ));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Parameter ':value' is bound to different values by the WHERE clause and the HAVING clause"
        );

        $repo->getSetOfAll();
    }
}
