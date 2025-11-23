<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use PHPUnit\Framework\TestCase;
use PDO;
use FasterPhp\DataModel\TestModel\ExternalItem;
use FasterPhp\DataModel\TestModel\ExternalRepository;

/**
 * Repository-level tests for FIELDS_EXTERNAL functionality.
 */
class ExternalFieldsRepositoryTest extends TestCase
{
    /**
     * Test that getFieldList() does NOT include FIELDS_EXTERNAL.
     */
    public function testGetFieldListExcludesExternalFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new ExternalRepository($pdo);
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getFieldList');
        $method->setAccessible(true);
        
        $fieldList = $method->invoke($repo);
        
        // Should include regular fields but NOT external fields
        $this->assertStringContainsString('`employees`.`empId` AS `id`', $fieldList);
        $this->assertStringContainsString('`employees`.`name`', $fieldList);
        $this->assertStringContainsString('`employees`.`departmentId`', $fieldList);
        $this->assertStringNotContainsString('departmentName', $fieldList);
    }
    
    /**
     * Test that getSelectClause() includes FIELDS_EXTERNAL via JOIN.
     */
    public function testSelectClauseIncludesExternalFieldsViaJoin(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new ExternalRepository($pdo);
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getSelectClause');
        $method->setAccessible(true);
        
        $selectClause = $method->invoke($repo);
        
        // Should include regular fields AND external fields from JOIN
        $this->assertStringContainsString('`employees`.`empId` AS `id`', $selectClause);
        $this->assertStringContainsString('`employees`.`name`', $selectClause);
        $this->assertStringContainsString('`employees`.`departmentId`', $selectClause);
        $this->assertStringContainsString('`departments`.`name` AS departmentName', $selectClause);
    }
    
    /**
     * Test that getFromClause() includes JOIN for external fields.
     */
    public function testFromClauseIncludesJoinForExternalFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new ExternalRepository($pdo);
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getFromClause');
        $method->setAccessible(true);
        
        $fromClause = $method->invoke($repo);
        
        // Should include LEFT JOIN with departments table
        $this->assertStringContainsString('`employees`', $fromClause);
        $this->assertStringContainsString('LEFT JOIN `departments`', $fromClause);
        $this->assertStringContainsString('`departments`.`deptId` = `employees`.`departmentId`', $fromClause);
    }
    
    /**
     * Test that INSERT SQL does NOT include FIELDS_EXTERNAL.
     */
    public function testInsertSqlExcludesExternalFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        
        // Create test tables
        $pdo->exec('
            CREATE TABLE employees (
                empId INTEGER PRIMARY KEY,
                name TEXT,
                departmentId INTEGER
            )
        ');
        
        $pdo->exec('
            CREATE TABLE departments (
                deptId INTEGER PRIMARY KEY,
                name TEXT
            )
        ');
        
        // Insert department
        $pdo->exec("INSERT INTO departments (deptId, name) VALUES (10, 'Engineering')");
        
        $repo = new ExternalRepository($pdo);
        
        $item = new ExternalItem([
            'empId' => 1,
            'name' => 'Alice',
            'departmentId' => 10,
            'departmentName' => 'Engineering',
        ]);
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('insertItem');
        $method->setAccessible(true);
        
        // Insert should succeed and exclude external fields
        $method->invoke($repo, $item);
        
        // Verify data was inserted without external fields
        $stmt = $pdo->query('SELECT * FROM employees WHERE empId = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertSame('Alice', $row['name']);
        $this->assertSame(10, (int)$row['departmentId']);
        
        // Verify departmentName column doesn't exist in employees table
        $this->assertArrayNotHasKey('departmentName', $row);
    }
    
    /**
     * Test that UPDATE SQL does NOT include FIELDS_EXTERNAL.
     */
    public function testUpdateSqlExcludesExternalFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        
        // Create test tables
        $pdo->exec('
            CREATE TABLE employees (
                empId INTEGER PRIMARY KEY,
                name TEXT,
                departmentId INTEGER
            )
        ');
        
        $pdo->exec('
            CREATE TABLE departments (
                deptId INTEGER PRIMARY KEY,
                name TEXT
            )
        ');
        
        // Insert test data
        $pdo->exec("INSERT INTO departments (deptId, name) VALUES (10, 'Engineering')");
        $pdo->exec("INSERT INTO employees (empId, name, departmentId) VALUES (1, 'Alice', 10)");
        
        $repo = new ExternalRepository($pdo);
        
        // Fetch item (will include departmentName from JOIN)
        $item = $repo->getItemWithId(1);
        
        // Change a regular field
        $item->setName('Bob');
        
        // Save changes
        $repo->saveItem($item);
        
        // Verify data was updated
        $stmt = $pdo->query('SELECT * FROM employees WHERE empId = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertSame('Bob', $row['name']);
        $this->assertSame(10, (int)$row['departmentId']);
    }
}
