<?php

/**
 * Tests for Data Model Repository class.
 */

namespace FasterPhp\DataModel;

use PHPUnit\Framework\TestCase;
use FasterPhp\DataModel\TestModel;
use PDO;

/**
 * Tests for Data Model Repository class.
 */
class RepositoryTest extends TestCase
{
    public function testGetDbNameNotSet(): void
    {
        $repo = new TestModel\NothingSetRepository($this->createStub(PDO::class));

        $this->expectException(\FasterPhp\DataModel\Exception::class);
        $this->expectExceptionMessage('Database name not set');

        $repo->getDbName();
    }

    public function testGetDbName(): void
    {
        $repo = new TestModel\ValidRepository($this->createStub(PDO::class));

        $this->assertSame('testdb', $repo->getDbName());
    }

    public function testGetTableNameNotSet(): void
    {
        $repo = new TestModel\NothingSetRepository($this->createStub(PDO::class));

        $this->expectException(\FasterPhp\DataModel\Exception::class);
        $this->expectExceptionMessage('Table name not set');

        $repo->getTableName();
    }

    public function testGetTableName(): void
    {
        $repo = new TestModel\ValidRepository($this->createStub(PDO::class));

        $this->assertSame('users', $repo->getTableName());
    }

    /**
     * Invoke the protected getComparison() method on a repository.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private function invokeGetComparison(Repository $repo, string $key, string $type, mixed $value): array
    {
        $method = (new \ReflectionClass($repo))->getMethod('getComparison');
        $method->setAccessible(true);
        return $method->invoke($repo, $key, $type, $value);
    }

    private function createRepository(): TestModel\ValidRepository
    {
        return new TestModel\ValidRepository($this->createStub(PDO::class));
    }

    /**
     * EQUALS with a null value uses SQL null semantics.
     */
    public function testEqualsNullProducesIsNull(): void
    {
        [$sql, $params] = $this->invokeGetComparison($this->createRepository(), 'age', Repository::EQUALS, null);

        $this->assertSame('`users`.`age` IS NULL', $sql);
        $this->assertSame([], $params);
    }

    public function testNotEqualsNullProducesIsNotNull(): void
    {
        [$sql, $params] = $this->invokeGetComparison($this->createRepository(), 'age', Repository::NOT_EQUALS, null);

        $this->assertSame('`users`.`age` IS NOT NULL', $sql);
        $this->assertSame([], $params);
    }

    public function testNullIsNeverCastToEmptyString(): void
    {
        $repo = $this->createRepository();

        foreach (array_keys(Repository::OPERATORS) as $type) {
            [, $params] = $this->invokeGetComparison($repo, 'age', $type, null);
            $this->assertNotContains('', $params, "Search type '$type' bound an empty string for null");
        }
    }

    /**
     * Characterisation: an empty array currently produces IS NULL rather than a no-match fragment.
     */
    public function testCharacterisationEmptyArray(): void
    {
        [$sql, $params] = $this->invokeGetComparison($this->createRepository(), 'age', Repository::EQUALS, []);

        // Current behaviour: the opposite of the conventional reading of an empty IN list.
        $this->assertSame('`users`.`age` IS NULL', $sql);
        $this->assertSame([], $params);
    }

    /**
     * Characterisation: an array containing null matches the non-null values or null.
     */
    public function testCharacterisationArrayContainingNull(): void
    {
        [$sql, $params] = $this->invokeGetComparison($this->createRepository(), 'age', Repository::EQUALS, [1, null]);

        $this->assertSame('(`users`.`age` = :age OR `users`.`age` IS NULL)', $sql);
        $this->assertSame([':age' => '1'], $params);
    }

    /**
     * Characterisation: an array whose only member is null produces IS NULL.
     */
    public function testCharacterisationArrayOfOnlyNull(): void
    {
        [$sql, $params] = $this->invokeGetComparison($this->createRepository(), 'age', Repository::EQUALS, [null]);

        $this->assertSame('`users`.`age` IS NULL', $sql);
        $this->assertSame([], $params);
    }

    /**
     * Characterisation: duplicate array values are bound once each.
     */
    public function testCharacterisationArrayWithDuplicateValues(): void
    {
        [$sql, $params] = $this->invokeGetComparison(
            $this->createRepository(),
            'age',
            Repository::EQUALS,
            [1, 2, 2, 3]
        );

        $this->assertSame('(`users`.`age` IN (:age_0,:age_1,:age_2))', $sql);
        $this->assertSame([':age_0' => 1, ':age_1' => 2, ':age_2' => 3], $params);
    }

    public function testGetIdField(): void
    {
        $repo = new TestModel\ValidRepository($this->createStub(PDO::class));

        $this->assertSame('userId', $repo->getIdField());
    }
}
