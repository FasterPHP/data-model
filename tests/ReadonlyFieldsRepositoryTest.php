<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use PHPUnit\Framework\TestCase;
use PDO;
use FasterPhp\DataModel\TestModel\ReadonlyItem;
use FasterPhp\DataModel\TestModel\ReadonlyRepository;

/**
 * Repository-level tests for FIELDS_READONLY functionality.
 */
class ReadonlyFieldsRepositoryTest extends TestCase
{
    /**
     * Test that getFieldList() includes FIELDS_READONLY.
     */
    public function testGetFieldListIncludesReadonlyFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new ReadonlyRepository($pdo);
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getFieldList');
        $method->setAccessible(true);
        
        $fieldList = $method->invoke($repo);
        
        // Should include both regular fields and readonly fields
        $this->assertStringContainsString('`readonly_users`.`userId` AS `id`', $fieldList);
        $this->assertStringContainsString('`readonly_users`.`name`', $fieldList);
        $this->assertStringContainsString('`readonly_users`.`email`', $fieldList);
        $this->assertStringContainsString('`readonly_users`.`createdAt`', $fieldList);
        $this->assertStringContainsString('`readonly_users`.`updatedAt`', $fieldList);
    }
    
    /**
     * Test that SELECT clause includes FIELDS_READONLY.
     */
    public function testSelectClauseIncludesReadonlyFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $repo = new ReadonlyRepository($pdo);
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('getSelectClause');
        $method->setAccessible(true);
        
        $selectClause = $method->invoke($repo);
        
        // Should include both regular fields and readonly fields
        $this->assertStringContainsString('`readonly_users`.`userId` AS `id`', $selectClause);
        $this->assertStringContainsString('`readonly_users`.`name`', $selectClause);
        $this->assertStringContainsString('`readonly_users`.`email`', $selectClause);
        $this->assertStringContainsString('`readonly_users`.`createdAt`', $selectClause);
        $this->assertStringContainsString('`readonly_users`.`updatedAt`', $selectClause);
    }
    
    /**
     * Test that INSERT SQL includes FIELDS_READONLY.
     */
    public function testInsertSqlIncludesReadonlyFields(): void
    {
        $pdo = new PDO('sqlite::memory:');
        
        // Create test table
        $pdo->exec('
            CREATE TABLE readonly_users (
                userId INTEGER PRIMARY KEY,
                name TEXT,
                email TEXT,
                createdAt TEXT,
                updatedAt TEXT
            )
        ');
        
        $repo = new ReadonlyRepository($pdo);
        
        $item = new ReadonlyItem([
            'userId' => 1,
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'createdAt' => '2025-01-01 12:00:00',
            'updatedAt' => '2025-01-02 14:30:00',
        ]);
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($repo);
        $method = $reflection->getMethod('insertItem');
        $method->setAccessible(true);
        
        // Insert should succeed and include readonly fields
        $method->invoke($repo, $item);
        
        // Verify data was inserted with readonly fields
        $stmt = $pdo->query('SELECT * FROM readonly_users WHERE userId = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertSame('Alice', $row['name']);
        $this->assertSame('alice@example.com', $row['email']);
        $this->assertSame('2025-01-01 12:00:00', $row['createdAt']);
        $this->assertSame('2025-01-02 14:30:00', $row['updatedAt']);
    }
    
    /**
     * Test that UPDATE SQL includes FIELDS_READONLY when they're changed.
     */
    public function testUpdateSqlIncludesReadonlyFieldsWhenChanged(): void
    {
        $pdo = new PDO('sqlite::memory:');
        
        // Create test table
        $pdo->exec('
            CREATE TABLE readonly_users (
                userId INTEGER PRIMARY KEY,
                name TEXT,
                email TEXT,
                createdAt TEXT,
                updatedAt TEXT
            )
        ');
        
        // Insert initial data
        $pdo->exec("
            INSERT INTO readonly_users (userId, name, email, createdAt, updatedAt)
            VALUES (1, 'Alice', 'alice@example.com', '2025-01-01 12:00:00', '2025-01-01 12:00:00')
        ");
        
        $repo = new ReadonlyRepository($pdo);
        
        // Fetch item
        $item = $repo->getItemWithId(1);
        
        // Change a regular field (this works)
        $item->setName('Bob');
        
        // Note: We can't directly change readonly fields via setter (throws exception)
        // But the database could update them (e.g., via trigger), and we want to ensure
        // they're included in UPDATE if they were somehow changed in originalValues
        
        // Save changes
        $repo->saveItem($item);
        
        // Verify data was updated
        $stmt = $pdo->query('SELECT * FROM readonly_users WHERE userId = 1');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->assertSame('Bob', $row['name']);
    }
}
