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
     * An identity lookup against a hand-written query constrains the id column, qualified with
     * the table name, so it can only return the requested row.
     */
    public function testIdentityLookupOnHandWrittenQueryConstrainsTheId(): void
    {
        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setHandWrittenQuery($this->createQuery());

        $repo->getItemWithId(2);

        $this->assertMatchesRegularExpression(
            '/\nWHERE \(`accounts`\.`status` = :status\) AND \(`users`\.`userId` = (:users_userId_\w+)\)/',
            $this->executions[0]['sql']
        );
        $this->assertSame(['active', '2'], array_values($this->executions[0]['params']));
    }

    /**
     * A filter on a declared field narrows a hand-written query in addition to its own condition,
     * qualified with the table name.
     */
    public function testFilterNarrowsHandWrittenQuery(): void
    {
        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setHandWrittenQuery($this->createQuery());

        $repo->getSetWithParams(['name' => 'Alice']);

        $this->assertStringEndsWith(
            "\nWHERE (`accounts`.`status` = :status) AND (`users`.`name` = :name)",
            $this->executions[0]['sql']
        );
        $this->assertSame([':status' => 'active', ':name' => 'Alice'], $this->executions[0]['params']);
    }

    /**
     * An OR in a hand-written WHERE keeps its meaning when a filter is combined with it.
     */
    public function testOrConditionOfHandWrittenQueryKeepsItsMeaning(): void
    {
        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setHandWrittenQuery($this->createQuery()->with(where: new SqlFragment(
                '`accounts`.`status` = :q_active OR `accounts`.`status` = :q_trial',
                [':q_active' => 'active', ':q_trial' => 'trial'],
            )));

        $repo->getSetWithParams(['name' => 'Alice']);

        $this->assertStringEndsWith(
            "\nWHERE (`accounts`.`status` = :q_active OR `accounts`.`status` = :q_trial)"
            . " AND (`users`.`name` = :name)",
            $this->executions[0]['sql']
        );
        $this->assertSame(
            [':q_active' => 'active', ':q_trial' => 'trial', ':name' => 'Alice'],
            $this->executions[0]['params']
        );
    }

    /**
     * A filter on an aggregate field is combined with the hand-written query's own HAVING, and
     * the WHERE clause is left as written.
     */
    public function testAggregateFilterIsCombinedWithExistingHaving(): void
    {
        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setHandWrittenQuery($this->createQuery()->with(
                select: new SqlFragment('`users`.`userId` AS `id`, `users`.`name`, COUNT(*) AS accountCount'),
                groupBy: new SqlFragment('`users`.`userId`'),
                having: new SqlFragment('COUNT(*) > :q_minimum', [':q_minimum' => 1]),
            ));

        $repo->getSetWithParams(['accountCount' => 3], ['accountCount' => Repository::LESS]);

        $sql = $this->executions[0]['sql'];
        $this->assertStringContainsString("\nWHERE `accounts`.`status` = :status\n", $sql);
        $this->assertStringEndsWith(
            "\nHAVING (COUNT(*) > :q_minimum) AND (`accountCount` < :accountCount)",
            $sql
        );
        $this->assertSame(
            [':status' => 'active', ':q_minimum' => 1, ':accountCount' => '3'],
            $this->executions[0]['params']
        );
    }

    /**
     * With no filters, the hand-written query is executed unchanged.
     */
    public function testNoFiltersLeaveHandWrittenQueryUnchanged(): void
    {
        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setHandWrittenQuery($this->createQuery());

        $repo->getSetWithParams([]);

        $rendered = $this->createQuery()->render();
        $this->assertSame($rendered->getSql(), $this->executions[0]['sql']);
        $this->assertSame($rendered->getParams(), $this->executions[0]['params']);
    }

    /**
     * A filter binding a parameter the hand-written query binds to a different value throws
     * before any statement is prepared.
     */
    public function testFilterCollidingWithHandWrittenParameterThrows(): void
    {
        $repo = (new HandWrittenRepository($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setHandWrittenQuery($this->createQuery());

        try {
            $repo->getSetWithParams(['status' => 'closed']);
            $this->fail('Expected an exception for the colliding parameter');
        } catch (Exception $e) {
            $this->assertSame(
                "Parameter ':status' is bound to different values by the WHERE clause and the condition ANDed onto it",
                $e->getMessage()
            );
        }
        $this->assertSame([], $this->executions);
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
