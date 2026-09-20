<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\TestModel\AggregateRepository;
use FasterPhp\DataModel\TestModel\FromOverrideRepository;
use FasterPhp\DataModel\TestModel\HookItem;
use FasterPhp\DataModel\TestModel\HookRepository;
use FasterPhp\DataModel\TestModel\HookSet;
use FasterPhp\DataModel\TestModel\JoinedRepository;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the repository query hook: that every SELECT is built through it, that its default
 * implementation composes the existing clause hooks, and that filters reach it.
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
     * Build the SQL a repository's query hook produces for a set of filters.
     *
     * @param array<string, mixed>  $params Filters to apply.
     * @param array<string, string> $types  Search type per filter key.
     */
    private function hookSql(Repository $repo, array $params = [], array $types = []): string
    {
        $method = (new \ReflectionClass($repo))->getMethod('buildSelectQuery');
        $method->setAccessible(true);
        return $method->invoke($repo, $params, $types)->render()->getSql();
    }

    /* -------------------------------
     * Repository SELECTs are built through a single query hook
     * ----------------------------- */

    public function testSetRetrievalUsesTheHook(): void
    {
        $repo = (new HookRepository($this->createRecordingPdo()))->setMaxItemsPerPage(null);

        $repo->getSetWithParams(['age' => 21]);

        $this->assertCount(1, $repo->hookResults);
        $this->assertCount(1, $this->executions);
        $this->assertSame($repo->hookResults[0]->render()->getSql(), $this->executions[0]['sql']);
        $this->assertSame($repo->hookResults[0]->render()->getParams(), $this->executions[0]['params']);
    }

    public function testItemRetrievalUsesTheHook(): void
    {
        $this->rows = [['id' => 1, 'name' => 'Alice', 'age' => 21]];
        $repo = new HookRepository($this->createRecordingPdo());

        $item = $repo->getItemWithParams(['age' => 21]);

        $this->assertInstanceOf(HookItem::class, $item);
        $this->assertCount(1, $repo->hookResults);
        $this->assertCount(1, $this->executions);
        // The one-row limit is the paginator's; the statement is otherwise the hook's query.
        $this->assertStringStartsWith(
            $repo->hookResults[0]->render()->getSql(),
            $this->executions[0]['sql']
        );
        $this->assertStringEndsWith('LIMIT 1', $this->executions[0]['sql']);
        $this->assertSame($repo->hookResults[0]->render()->getParams(), $this->executions[0]['params']);
    }

    public function testFiltersAndSearchTypesReachTheHook(): void
    {
        $repo = (new HookRepository($this->createRecordingPdo()))->setMaxItemsPerPage(null);

        $repo->getSetWithParams(['age' => 21, 'name' => 'Al'], ['name' => Repository::STARTS]);

        $this->assertSame(
            [['params' => ['age' => 21, 'name' => 'Al'], 'types' => ['name' => Repository::STARTS]]],
            $repo->hookCalls
        );
    }

    public function testGetSetOfAllReachesTheHookWithNoFilters(): void
    {
        $repo = (new HookRepository($this->createRecordingPdo()))->setMaxItemsPerPage(null);

        $set = $repo->getSetOfAll();

        $this->assertInstanceOf(HookSet::class, $set);
        $this->assertSame([['params' => [], 'types' => []]], $repo->hookCalls);
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
        $query = $method->invoke($repo, []);

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

        $this->assertSame($join, $method->invoke($repo, [])->getFrom()->getSql());

        $repo->getSetOfAll();
        $this->assertStringContainsString($join, $this->executions[0]['sql']);
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
