<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use PHPUnit\Framework\TestCase;
use PDO;
use FasterPhp\DataModel\TestModel\AggregateItem;
use FasterPhp\DataModel\TestModel\AggregateRepository;
use FasterPhp\DataModel\TestModel\ExternalRepository;
use FasterPhp\DataModel\TestModel\ReadonlyRepository;

/**
 * Repository-level tests for FIELDS_AGGREGATE functionality.
 */
class AggregateFieldsRepositoryTest extends TestCase
{
    /**
     * Test that getFieldList() does NOT include FIELDS_AGGREGATE.
     */
    public function testGetFieldListExcludesAggregateFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new AggregateRepository($pdo);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getFieldList');
        $method->setAccessible(true);

        $fieldList = $method->invoke($repo);

        // Should include regular fields but NOT aggregate fields
        $this->assertStringContainsString('`orders`.`orderId` AS `id`', $fieldList);
        $this->assertStringContainsString('`orders`.`userId`', $fieldList);
        $this->assertStringContainsString('`orders`.`status`', $fieldList);
        $this->assertStringNotContainsString('totalAmount', $fieldList);
        $this->assertStringNotContainsString('orderCount', $fieldList);
    }

    /**
     * Test that getSelectClause() includes FIELDS_AGGREGATE via aggregation functions.
     */
    public function testSelectClauseIncludesAggregateFieldsViaFunctions(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new AggregateRepository($pdo);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getSelectClause');
        $method->setAccessible(true);

        $selectClause = $method->invoke($repo);

        // Should include regular fields AND aggregate fields from functions
        $this->assertStringContainsString('`orders`.`orderId` AS `id`', $selectClause);
        $this->assertStringContainsString('`orders`.`userId`', $selectClause);
        $this->assertStringContainsString('`orders`.`status`', $selectClause);
        $this->assertStringContainsString('SUM(`orders`.`amount`) AS totalAmount', $selectClause);
        $this->assertStringContainsString('COUNT(*) AS orderCount', $selectClause);
    }

    /**
     * Test that getGroupByClause() is generated when FIELDS_AGGREGATE is not empty.
     */
    public function testGroupByClauseGeneratedWhenAggregateFieldsPresent(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new AggregateRepository($pdo);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getGroupByClause');
        $method->setAccessible(true);

        $groupByClause = $method->invoke($repo);

        // Should generate GROUP BY clause with ID field
        $this->assertStringContainsString('`orders`.`orderId`', $groupByClause);
    }

    /**
     * Test that getGroupByClause() is empty when FIELDS_AGGREGATE is empty.
     */
    public function testGroupByClauseEmptyWhenNoAggregateFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new ReadonlyRepository($pdo); // This has no FIELDS_AGGREGATE

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getGroupByClause');
        $method->setAccessible(true);

        $groupByClause = $method->invoke($repo);

        // Should be empty string
        $this->assertSame('', $groupByClause);
    }

    /**
     * Test that WHERE clause is used for non-aggregate fields.
     */
    public function testWhereClauseUsedForNonAggregateFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new AggregateRepository($pdo);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getWhereSqlAndParams');
        $method->setAccessible(true);

        // Test with non-aggregate field
        [$whereSql, $whereParams] = $method->invoke($repo, ['userId' => 100]);

        // Should generate WHERE clause for non-aggregate field
        $this->assertStringContainsString('`orders`.`userId`', $whereSql);
        $this->assertArrayHasKey(':userId', $whereParams);
        $this->assertEquals(100, $whereParams[':userId']);
    }

    /**
     * Test that WHERE clause excludes aggregate fields.
     */
    public function testWhereClauseExcludesAggregateFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new AggregateRepository($pdo);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getWhereSqlAndParams');
        $method->setAccessible(true);

        // Test with aggregate field
        [$whereSql, $whereParams] = $method->invoke($repo, ['totalAmount' => 1000]);

        // Should NOT generate WHERE clause for aggregate field
        $this->assertSame('', $whereSql);
        $this->assertEmpty($whereParams);
    }

    /**
     * Test that HAVING clause is used for aggregate fields.
     */
    public function testHavingClauseUsedForAggregateFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new AggregateRepository($pdo);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getHavingSqlAndParams');
        $method->setAccessible(true);

        // Test with aggregate field
        [$havingSql, $havingParams] = $method->invoke($repo, ['totalAmount' => 1000]);

        // Should generate HAVING clause for aggregate field (bare, not table-qualified)
        $this->assertStringContainsString('`totalAmount`', $havingSql);
        $this->assertStringNotContainsString('`orders`.`totalAmount`', $havingSql);
        $this->assertArrayHasKey(':totalAmount', $havingParams);
        $this->assertEquals(1000, $havingParams[':totalAmount']);
    }

    /**
     * Test that HAVING clause excludes non-aggregate fields.
     */
    public function testHavingClauseExcludesNonAggregateFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new AggregateRepository($pdo);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getHavingSqlAndParams');
        $method->setAccessible(true);

        // Test with non-aggregate field
        [$havingSql, $havingParams] = $method->invoke($repo, ['userId' => 100]);

        // Should NOT generate HAVING clause for non-aggregate field
        $this->assertSame('', $havingSql);
        $this->assertEmpty($havingParams);
    }

    /**
     * Test that mixed params are split correctly between WHERE and HAVING.
     */
    public function testMixedParamsSplitBetweenWhereAndHaving(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new AggregateRepository($pdo);

        // Use reflection to access protected methods
        $reflection = new \ReflectionClass($repo);
        $whereMethod = $reflection->getMethod('getWhereSqlAndParams');
        $whereMethod->setAccessible(true);
        $havingMethod = $reflection->getMethod('getHavingSqlAndParams');
        $havingMethod->setAccessible(true);

        // Test with mixed params
        $params = [
            'userId' => 100,
            'status' => 'completed',
            'totalAmount' => 1000,
            'orderCount' => 5,
        ];

        [$whereSql, $whereParams] = $whereMethod->invoke($repo, $params);
        [$havingSql, $havingParams] = $havingMethod->invoke($repo, $params);

        // WHERE should have non-aggregate fields
        $this->assertStringContainsString('`orders`.`userId`', $whereSql);
        $this->assertStringContainsString('`orders`.`status`', $whereSql);
        $this->assertArrayHasKey(':userId', $whereParams);
        $this->assertArrayHasKey(':status', $whereParams);

        // HAVING should have aggregate fields (bare, not table-qualified)
        $this->assertStringContainsString('`totalAmount`', $havingSql);
        $this->assertStringNotContainsString('`orders`.`totalAmount`', $havingSql);
        $this->assertStringContainsString('`orderCount`', $havingSql);
        $this->assertStringNotContainsString('`orders`.`orderCount`', $havingSql);
        $this->assertArrayHasKey(':totalAmount', $havingParams);
        $this->assertArrayHasKey(':orderCount', $havingParams);
    }

    /**
     * Test that INSERT SQL does NOT include FIELDS_AGGREGATE.
     */
    public function testInsertSqlExcludesAggregateFields(): void
    {
        $pdo = new PDO('sqlite::memory:');

        // Create test table
        $pdo->exec('
            CREATE TABLE orders (
                orderId INTEGER PRIMARY KEY,
                userId INTEGER,
                status TEXT,
                amount INTEGER
            )
        ');

        $repo = new AggregateRepository($pdo);

        $item = new AggregateItem([
            'orderId' => 1,
            'userId' => 100,
            'status' => 'completed',
            'totalAmount' => 15000,
            'orderCount' => 5,
        ]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('insertItem');
        $method->setAccessible(true);

        // Insert should succeed and exclude aggregate fields
        $method->invoke($repo, $item);

        // Verify data was inserted without aggregate fields
        $stmt = $pdo->query('SELECT * FROM orders WHERE orderId = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(100, (int)$row['userId']);
        $this->assertSame('completed', $row['status']);

        // Verify aggregate columns don't exist in orders table
        $this->assertArrayNotHasKey('totalAmount', $row);
        $this->assertArrayNotHasKey('orderCount', $row);
    }

    /**
     * Test that UPDATE SQL does NOT include FIELDS_AGGREGATE.
     */
    public function testUpdateSqlExcludesAggregateFields(): void
    {
        $pdo = new PDO('sqlite::memory:');

        // Create test table
        $pdo->exec('
            CREATE TABLE orders (
                orderId INTEGER PRIMARY KEY,
                userId INTEGER,
                status TEXT,
                amount INTEGER
            )
        ');

        // Insert test data
        $pdo->exec("INSERT INTO orders (orderId, userId, status, amount) VALUES (1, 100, 'pending', 5000)");

        $repo = new AggregateRepository($pdo);

        // Fetch item
        $item = $repo->getItemWithId(1);

        // Change a regular field
        $item->setStatus('completed');

        // Save changes
        $repo->saveItem($item);

        // Verify data was updated
        $stmt = $pdo->query('SELECT * FROM orders WHERE orderId = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('completed', $row['status']);
        $this->assertSame(100, (int)$row['userId']);
    }

    /**
     * Test that aggregate fields are NOT table-qualified in getComparison().
     */
    public function testAggregateFieldNotTableQualified(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new AggregateRepository($pdo);

        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getComparison');
        $method->setAccessible(true);

        [$sql] = $method->invoke($repo, 'totalAmount', 'equals', 1000);

        $this->assertStringContainsString('`totalAmount`', $sql);
        $this->assertStringNotContainsString('`orders`.`totalAmount`', $sql);
    }

    /**
     * Test that external fields are NOT table-qualified in getComparison().
     */
    public function testExternalFieldNotTableQualified(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new ExternalRepository($pdo);

        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getComparison');
        $method->setAccessible(true);

        [$sql] = $method->invoke($repo, 'departmentName', 'equals', 'Engineering');

        $this->assertStringContainsString('`departmentName`', $sql);
        $this->assertStringNotContainsString('`employees`.`departmentName`', $sql);
    }

    /**
     * Test that readonly fields ARE table-qualified in getComparison().
     */
    public function testReadonlyFieldIsTableQualified(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new ReadonlyRepository($pdo);

        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getComparison');
        $method->setAccessible(true);

        [$sql] = $method->invoke($repo, 'createdAt', 'equals', '2026-01-01');

        $this->assertStringContainsString('`readonly_users`.`createdAt`', $sql);
    }

    /**
     * Test that regular FIELDS are table-qualified in getComparison().
     */
    public function testRegularFieldIsTableQualified(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new AggregateRepository($pdo);

        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getComparison');
        $method->setAccessible(true);

        [$sql] = $method->invoke($repo, 'userId', 'equals', 100);

        $this->assertStringContainsString('`orders`.`userId`', $sql);
    }

    /**
     * Test that dot-qualified keys are used as-is in getComparison().
     */
    public function testDotQualifiedKeyUsedAsIs(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new AggregateRepository($pdo);

        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getComparison');
        $method->setAccessible(true);

        [$sql] = $method->invoke($repo, 'a.createdDate', 'equals', '2026-01-01');

        $this->assertStringContainsString('`a`.`createdDate`', $sql);
    }
}
