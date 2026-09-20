<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\Sql;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the SqlClause value object.
 */
final class SqlClauseTest extends TestCase
{
    /**
     * A clause constructed from a fragment and a parameter map exposes both unchanged.
     */
    public function testClauseExposesItsFragmentAndParameters(): void
    {
        $clause = new SqlClause('`users`.`age` >= :age', [':age' => 21]);

        $this->assertSame('`users`.`age` >= :age', $clause->getSql());
        $this->assertSame([':age' => 21], $clause->getParams());
    }

    /**
     * A clause constructed from a fragment alone binds nothing.
     */
    public function testClauseWithNoParameters(): void
    {
        $clause = new SqlClause('`users`.`age` IS NULL');

        $this->assertSame('`users`.`age` IS NULL', $clause->getSql());
        $this->assertSame([], $clause->getParams());
    }

    /**
     * Writing to a clause's fragment fails rather than modifying it.
     */
    public function testClauseFragmentCannotBeAltered(): void
    {
        $clause = new SqlClause('`users`.`age` >= :age', [':age' => 21]);

        try {
            $clause->sql = 'anything else';
            $this->fail('Expected writing to a clause fragment to fail');
        } catch (\Error $e) {
            $this->assertStringContainsString('readonly', $e->getMessage());
        }

        $this->assertSame('`users`.`age` >= :age', $clause->getSql());
    }

    /**
     * Writing to a clause's parameters fails rather than modifying them.
     */
    public function testClauseParametersCannotBeAltered(): void
    {
        $clause = new SqlClause('`users`.`age` >= :age', [':age' => 21]);

        try {
            $clause->params = [':age' => 99];
            $this->fail('Expected writing to clause parameters to fail');
        } catch (\Error $e) {
            $this->assertStringContainsString('readonly', $e->getMessage());
        }

        $this->assertSame([':age' => 21], $clause->getParams());
    }
}
