<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\Paginator;

use FasterPhp\DataModel\Sort;
use FasterPhp\DataModel\Sql\SqlFragment;
use FasterPhp\DataModel\Sql\SqlQuery;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

/**
 * Tests for handing SqlPaginator a whole query rather than SQL and parameters separately.
 */
class SqlPaginatorQueryTest extends TestCase
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

    private function createQuery(int $age = 21): SqlQuery
    {
        return new SqlQuery(
            new SqlFragment('`users`.`userId`, `users`.`name`'),
            new SqlFragment('`users`'),
            new SqlFragment('`users`.`age` >= :age', [':age' => $age]),
        );
    }

    /**
     * A query supplied as one value executes with its own parameters bound.
     */
    public function testQuerySuppliedAsOneValueExecutesWithItsParameters(): void
    {
        $paginator = (new SqlPaginator($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setQuery($this->createQuery());

        $paginator->getItems();

        $this->assertCount(1, $this->executions);
        $this->assertSame(
            "SELECT `users`.`userId`, `users`.`name` FROM `users`\nWHERE `users`.`age` >= :age",
            $this->executions[0]['sql']
        );
        $this->assertSame([':age' => 21], $this->executions[0]['params']);
    }

    /**
     * A query supplied this way is still sorted and paginated.
     */
    public function testQueryIsSortedAndPaginated(): void
    {
        $paginator = (new SqlPaginator($this->createRecordingPdo(), new Sort('users.name')))
            ->setMaxItemsPerPage(10)
            ->setQuery($this->createQuery());

        $paginator->getItems();

        $this->assertStringEndsWith(
            'ORDER BY `users`.`name` ASC LIMIT 10',
            $this->executions[0]['sql']
        );
    }

    /**
     * A different query replaces both the SQL and the parameters.
     */
    public function testDifferentQueryReplacesBothSqlAndParams(): void
    {
        $paginator = (new SqlPaginator($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setQuery($this->createQuery());

        $paginator->getItems();

        $replacement = new SqlQuery(
            new SqlFragment('`users`.`userId`'),
            new SqlFragment('`users`'),
            new SqlFragment('`users`.`name` = :name', [':name' => 'Alice']),
        );
        $paginator->setQuery($replacement);
        $paginator->getItems();

        $this->assertCount(2, $this->executions);
        $this->assertSame(
            "SELECT `users`.`userId` FROM `users`\nWHERE `users`.`name` = :name",
            $this->executions[1]['sql']
        );
        $this->assertSame([':name' => 'Alice'], $this->executions[1]['params']);
    }

    /**
     * Cached results from the previous query are discarded when a different one is supplied.
     */
    public function testDifferentQueryDiscardsCachedResults(): void
    {
        $paginator = (new SqlPaginator($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null)
            ->setQuery($this->createQuery());

        $this->rows = [['userId' => 1, 'name' => 'Alice']];
        $this->assertSame($this->rows, $paginator->getItems());

        // A second call with no change re-uses the cached rows rather than executing again.
        $paginator->getItems();
        $this->assertCount(1, $this->executions);

        $this->rows = [['userId' => 2, 'name' => 'Bob']];
        $paginator->setQuery($this->createQuery(65));

        $this->assertSame($this->rows, $paginator->getItems());
        $this->assertCount(2, $this->executions);
        $this->assertSame([':age' => 65], $this->executions[1]['params']);
    }

    /**
     * A query differing only in its bound values still discards the cached results.
     */
    public function testQueryDifferingOnlyInParamsDiscardsCachedResults(): void
    {
        $paginator = (new SqlPaginator($this->createRecordingPdo()))
            ->setMaxItemsPerPage(null);

        $sameSql = fn(string $name): SqlQuery => new SqlQuery(
            new SqlFragment('`users`.`userId`'),
            new SqlFragment('`users`'),
            new SqlFragment('`users`.`name` = :name', [':name' => $name]),
        );

        $paginator->setQuery($sameSql('Alice'));
        $paginator->getItems();

        $paginator->setQuery($sameSql('Bob'));
        $paginator->getItems();

        $this->assertCount(2, $this->executions);
        $this->assertSame([':name' => 'Bob'], $this->executions[1]['params']);
    }
}
