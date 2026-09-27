<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\Sql;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the SqlFragment value object.
 */
final class SqlFragmentTest extends TestCase
{
    /**
     * A fragment constructed from SQL and a parameter map exposes both unchanged.
     */
    public function testFragmentExposesItsSqlAndParameters(): void
    {
        $fragment = new SqlFragment('`users`.`age` >= :age', [':age' => 21]);

        $this->assertSame('`users`.`age` >= :age', $fragment->getSql());
        $this->assertSame([':age' => 21], $fragment->getParams());
    }

    /**
     * A fragment constructed from SQL alone binds nothing.
     */
    public function testFragmentWithNoParameters(): void
    {
        $fragment = new SqlFragment('`users`.`age` IS NULL');

        $this->assertSame('`users`.`age` IS NULL', $fragment->getSql());
        $this->assertSame([], $fragment->getParams());
    }

    /**
     * Writing to a fragment's SQL fails rather than modifying it.
     */
    public function testFragmentSqlCannotBeAltered(): void
    {
        $fragment = new SqlFragment('`users`.`age` >= :age', [':age' => 21]);

        try {
            $fragment->sql = 'anything else';
            $this->fail('Expected writing to fragment SQL to fail');
        } catch (\Error $e) {
            $this->assertStringContainsString('readonly', $e->getMessage());
        }

        $this->assertSame('`users`.`age` >= :age', $fragment->getSql());
    }

    /**
     * Writing to a fragment's parameters fails rather than modifying them.
     */
    public function testFragmentParametersCannotBeAltered(): void
    {
        $fragment = new SqlFragment('`users`.`age` >= :age', [':age' => 21]);

        try {
            $fragment->params = [':age' => 99];
            $this->fail('Expected writing to fragment parameters to fail');
        } catch (\Error $e) {
            $this->assertStringContainsString('readonly', $e->getMessage());
        }

        $this->assertSame([':age' => 21], $fragment->getParams());
    }
}
