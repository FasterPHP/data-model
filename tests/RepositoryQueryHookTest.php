<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\TestModel\AggregateRepository;
use FasterPhp\DataModel\TestModel\FromOverrideRepository;
use FasterPhp\DataModel\TestModel\HookItem;
use FasterPhp\DataModel\TestModel\HookRepository;
use FasterPhp\DataModel\TestModel\HookSet;
use FasterPhp\DataModel\TestModel\JoinedRepository;
use FasterPhp\DataModel\TestModel\ParticipantRepository;
use FasterPhp\DataModel\Sql\SqlFragment;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the repository query hook: that every SELECT is built through it, that its default
 * implementation composes the existing clause hooks, and that the repository applies filters to
 * whatever it returns.
 */
class RepositoryQueryHookTest extends TestCase
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

    /**
     * Build the SQL a repository retrieves with for a set of filters: the query hook's result
     * with the filters applied.
     *
     * @param array<string, mixed>  $params Filters to apply.
     * @param array<string, string> $types  Search type per filter key.
     */
    private function hookSql(Repository $repo, array $params = [], array $types = []): string
    {
        $method = new \ReflectionMethod(Repository::class, 'buildRetrievalQuery');
        $method->setAccessible(true);
        return $method->invoke($repo, $params, $types)->render()->getSql();
    }

    /* -------------------------------
     * Repository SELECTs are built through a single filter-free query hook
     * ----------------------------- */

    public function testSetRetrievalUsesTheHook(): void
    {
        $repo = (new HookRepository($this->createRecordingPdo()))->setMaxItemsPerPage(null);

        $repo->getSetWithParams(['age' => 21]);

        $expected = $repo->hookResults[0]
            ->andWhere(new SqlFragment('`users`.`age` = :age', [':age' => '21']))
            ->render();

        $this->assertCount(1, $repo->hookResults);
        $this->assertCount(1, $this->executions);
        $this->assertSame($expected->getSql(), $this->executions[0]['sql']);
        $this->assertSame($expected->getParams(), $this->executions[0]['params']);
    }

    public function testItemRetrievalUsesTheHook(): void
    {
        $this->rows = [['id' => 1, 'name' => 'Alice', 'age' => 21]];
        $repo = new HookRepository($this->createRecordingPdo());

        $item = $repo->getItemWithParams(['age' => 21]);

        $expected = $repo->hookResults[0]
            ->andWhere(new SqlFragment('`users`.`age` = :age', [':age' => '21']))
            ->render();

        $this->assertInstanceOf(HookItem::class, $item);
        $this->assertCount(1, $repo->hookResults);
        $this->assertCount(1, $this->executions);
        // The one-row limit is the paginator's; the statement is otherwise the filtered hook query.
        $this->assertStringStartsWith($expected->getSql(), $this->executions[0]['sql']);
        $this->assertStringEndsWith('LIMIT 1', $this->executions[0]['sql']);
        $this->assertSame($expected->getParams(), $this->executions[0]['params']);
    }

    /**
     * The hook is called without the filters or search types, which still constrain the SQL.
     */
    public function testHookReceivesNoFiltersYetFiltersConstrainTheSql(): void
    {
        $repo = (new HookRepository($this->createRecordingPdo()))->setMaxItemsPerPage(null);

        $repo->getSetWithParams(['age' => 21, 'name' => 'Al'], ['name' => Repository::STARTS]);

        $this->assertSame([[]], $repo->hookCalls);
        $this->assertStringNotContainsString('WHERE', $repo->hookResults[0]->render()->getSql());
        $this->assertStringEndsWith(
            "\nWHERE `users`.`age` = :age AND `users`.`name` LIKE :name",
            $this->executions[0]['sql']
        );
        $this->assertSame([':age' => '21', ':name' => 'Al%'], $this->executions[0]['params']);
    }

    /**
     * An identity lookup reaches the hook without its id, which still constrains the SQL.
     */
    public function testItemRetrievalByIdCallsTheHookWithoutFilters(): void
    {
        $repo = new HookRepository($this->createRecordingPdo());

        $repo->getItemWithId(7);

        $this->assertSame([[]], $repo->hookCalls);
        $this->assertStringContainsString("\nWHERE `users`.`userId` = :users_userId_", $this->executions[0]['sql']);
        $this->assertSame(['7'], array_values($this->executions[0]['params']));
    }

    public function testGetSetOfAllCallsTheHookWithoutFilters(): void
    {
        $repo = (new HookRepository($this->createRecordingPdo()))->setMaxItemsPerPage(null);

        $set = $repo->getSetOfAll();

        $this->assertInstanceOf(HookSet::class, $set);
        $this->assertSame([[]], $repo->hookCalls);
        $this->assertSame($repo->hookResults[0]->render()->getSql(), $this->executions[0]['sql']);
    }

    /* -------------------------------
     * The default query composes the existing clause hooks
     * ----------------------------- */

    public function testUnmodifiedRepositoryProducesUnchangedSql(): void
    {
        $repo = (new HookRepository($this->createRecordingPdo()))->setMaxItemsPerPage(null);

        $repo->getSetOfAll();

        $this->assertSame(
            'SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age` FROM `users`',
            $this->executions[0]['sql']
        );
    }

    public function testOverriddenSelectClauseIsReflected(): void
    {
        $repo = (new AggregateRepository($this->createRecordingPdo()))->setMaxItemsPerPage(null);

        $method = (new \ReflectionClass($repo))->getMethod('buildSelectQuery');
        $method->setAccessible(true);
        $query = $method->invoke($repo);

        $this->assertStringContainsString('SUM(`orders`.`amount`) AS totalAmount', $query->getSelect()->getSql());

        $repo->getSetOfAll();
        $this->assertStringContainsString('SUM(`orders`.`amount`) AS totalAmount', $this->executions[0]['sql']);
    }

    public function testOverriddenFromClauseIsReflected(): void
    {
        $repo = (new FromOverrideRepository($this->createRecordingPdo()))->setMaxItemsPerPage(null);
        $join = '`users` JOIN `accounts` ON `accounts`.`userId` = `users`.`userId`';

        $method = (new \ReflectionClass($repo))->getMethod('buildSelectQuery');
        $method->setAccessible(true);

        $this->assertSame($join, $method->invoke($repo)->getFrom()->getSql());

        $repo->getSetOfAll();
        $this->assertStringContainsString($join, $this->executions[0]['sql']);
    }

    /**
     * A filter on the ID column's bare name is qualified with the base table, so it stays
     * unambiguous when the from clause joins a table with a column of the same name.
     */
    public function testIdFilterOnJoinedRepositoryIsQualified(): void
    {
        $repo = (new FromOverrideRepository($this->createRecordingPdo()))->setMaxItemsPerPage(null);

        $repo->getSetWithParams(['userId' => 7]);

        $this->assertStringContainsString('JOIN `accounts` ON `accounts`.`userId`', $this->executions[0]['sql']);
        $this->assertStringEndsWith("\nWHERE `users`.`userId` = :userId", $this->executions[0]['sql']);
        $this->assertSame([':userId' => '7'], $this->executions[0]['params']);
    }

    /**
     * A repository overriding both clause hooks, as joined repositories typically do.
     */
    public function testOverridingBothClauseHooksTogether(): void
    {
        $repo = (new JoinedRepository($this->createRecordingPdo()))->setMaxItemsPerPage(null);

        $repo->getSetWithParams(['moduleId' => 7]);

        $sql = $this->executions[0]['sql'];
        $this->assertStringContainsString('ROUND(100 * moduleAttempts.currentTime / m.duration)', $sql);
        $this->assertStringContainsString('JOIN modules m ON m.moduleId = moduleAttempts.moduleId', $sql);
        $this->assertStringContainsString("\nWHERE `moduleAttempts`.`moduleId` = :moduleId", $sql);
        $this->assertSame([':moduleId' => '7'], $this->executions[0]['params']);
    }

    /**
     * Filters injected through the where hook apply to every retrieval, including one with no
     * caller filters, and the caller's own filters appear alongside them.
     */
    public function testFiltersInjectedThroughTheWhereHookApplyToEveryRetrieval(): void
    {
        $injected = "`users`.`role` = :role AND `users`.`status` = :status"
            . " AND `a2`.`courseAttemptId` IS NULL";
        $injectedParams = [':role' => 'staff', ':status' => 'active'];

        $repo = (new ParticipantRepository($this->createRecordingPdo()))->setMaxItemsPerPage(null);

        $repo->getSetOfAll();
        $repo->getSetWithParams(['name' => 'Alice']);
        $repo->getItemWithId(7);

        $this->assertCount(3, $this->executions);

        $this->assertStringEndsWith("\nWHERE $injected", $this->executions[0]['sql']);
        $this->assertSame($injectedParams, $this->executions[0]['params']);

        $this->assertStringEndsWith("\nWHERE `name` = :name AND $injected", $this->executions[1]['sql']);
        $this->assertSame([':name' => 'Alice'] + $injectedParams, $this->executions[1]['params']);

        $this->assertStringContainsString("\nWHERE `users`.`userId` = :users_userId_", $this->executions[2]['sql']);
        $this->assertStringContainsString(" AND $injected", $this->executions[2]['sql']);
        $this->assertSame($injectedParams, array_slice($this->executions[2]['params'], 1));
    }

    /**
     * Filters still split between WHERE and HAVING according to field ownership.
     */
    public function testFiltersStillSplitBetweenWhereAndHaving(): void
    {
        $repo = new AggregateRepository($this->createRecordingPdo());

        $sql = $this->hookSql($repo, [
            'status' => 'completed',
            'totalAmount' => 1000,
        ]);

        $this->assertStringContainsString("\nWHERE `orders`.`status` = :status", $sql);
        $this->assertStringContainsString("\nGROUP BY `orders`.`orderId`", $sql);
        $this->assertStringContainsString("\nHAVING `totalAmount` = :totalAmount", $sql);
        $this->assertStringNotContainsString('`totalAmount` = :totalAmount AND', $sql);
    }

    /**
     * An aggregate-free repository contributes no GROUP BY or HAVING keyword.
     */
    public function testRepositoryWithoutAggregatesHasNoGroupByOrHaving(): void
    {
        $sql = $this->hookSql(new HookRepository($this->createRecordingPdo()), ['age' => 21]);

        $this->assertStringNotContainsString('GROUP BY', $sql);
        $this->assertStringNotContainsString('HAVING', $sql);
    }
}
