<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\Sql;

use FasterPhp\DataModel\Exception;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the SqlQuery value object.
 */
final class SqlQueryTest extends TestCase
{
    private function createQuery(): SqlQuery
    {
        return new SqlQuery(
            new SqlFragment('`users`.`userId`, COUNT(*) AS total'),
            new SqlFragment('`users`'),
            new SqlFragment('`users`.`age` >= :age', [':age' => 21]),
            new SqlFragment('`users`.`userId`'),
            new SqlFragment('`total` > :total', [':total' => 2]),
        );
    }

    /* -------------------------------
     * A query exposes every clause it holds
     * ----------------------------- */

    public function testRequiredClausesAreReadable(): void
    {
        $query = new SqlQuery(new SqlFragment('`users`.`name`'), new SqlFragment('`users`'));

        $this->assertSame('`users`.`name`', $query->getSelect()->getSql());
        $this->assertSame('`users`', $query->getFrom()->getSql());
    }

    public function testOptionalClausesAreReadableWhenPresent(): void
    {
        $query = $this->createQuery();

        $this->assertSame('`users`.`age` >= :age', $query->getWhere()->getSql());
        $this->assertSame([':age' => 21], $query->getWhere()->getParams());
        $this->assertSame('`users`.`userId`', $query->getGroupBy()->getSql());
        $this->assertSame('`total` > :total', $query->getHaving()->getSql());
    }

    public function testAbsentOptionalClausesAreReportedAsAbsent(): void
    {
        $query = new SqlQuery(new SqlFragment('`users`.`name`'), new SqlFragment('`users`'));

        $this->assertNull($query->getWhere());
        $this->assertNull($query->getGroupBy());
        $this->assertNull($query->getHaving());
    }

    /**
     * A clause whose fragment is empty is present, and so distinct from an absent one.
     */
    public function testEmptyClauseIsDistinctFromAbsentClause(): void
    {
        $query = new SqlQuery(new SqlFragment('`users`.`name`'), new SqlFragment('`users`'), new SqlFragment(''));

        $this->assertNotNull($query->getWhere());
        $this->assertSame('', $query->getWhere()->getSql());
    }

    /* -------------------------------
     * A query is immutable and derives rather than mutates
     * ----------------------------- */

    public function testReplacingAClauseReturnsANewQuery(): void
    {
        $query = $this->createQuery();

        $derived = $query->with(where: new SqlFragment('`users`.`age` < :age', [':age' => 65]));

        $this->assertNotSame($query, $derived);
        $this->assertSame('`users`.`age` < :age', $derived->getWhere()->getSql());
        $this->assertSame('`users`.`age` >= :age', $query->getWhere()->getSql());
    }

    public function testUnreplacedClausesAreCarriedOver(): void
    {
        $query = $this->createQuery();

        $derived = $query->with(where: new SqlFragment('1 = 1'));

        $this->assertSame('1 = 1', $derived->getWhere()->getSql());
        $this->assertSame($query->getSelect(), $derived->getSelect());
        $this->assertSame($query->getFrom(), $derived->getFrom());
        $this->assertSame($query->getGroupBy(), $derived->getGroupBy());
        $this->assertSame($query->getHaving(), $derived->getHaving());
    }

    public function testSeveralClausesReplacedAtOnce(): void
    {
        $query = $this->createQuery();

        $derived = $query->with(
            select: new SqlFragment('`users`.`name`'),
            from: new SqlFragment('`people`'),
            having: new SqlFragment('`total` < :total', [':total' => 9]),
        );

        $this->assertSame('`users`.`name`', $derived->getSelect()->getSql());
        $this->assertSame('`people`', $derived->getFrom()->getSql());
        $this->assertSame('`total` < :total', $derived->getHaving()->getSql());
        $this->assertSame($query->getWhere(), $derived->getWhere());
    }

    public function testOptionalClauseCanBeRemoved(): void
    {
        $query = $this->createQuery();

        $derived = $query->with(where: SqlQuery::NONE, having: SqlQuery::NONE);

        $this->assertNull($derived->getWhere());
        $this->assertNull($derived->getHaving());
        $this->assertNotNull($query->getWhere());
        $this->assertNotNull($query->getHaving());
    }

    /* -------------------------------
     * A condition can be ANDed onto WHERE or HAVING
     * ----------------------------- */

    public function testConditionBecomesAnAbsentWhereClauseUngrouped(): void
    {
        $query = new SqlQuery(new SqlFragment('`users`.`name`'), new SqlFragment('`users`'));

        $derived = $query->andWhere(new SqlFragment('`users`.`age` >= :age', [':age' => 21]));

        $this->assertSame('`users`.`age` >= :age', $derived->getWhere()->getSql());
        $this->assertSame([':age' => 21], $derived->getWhere()->getParams());
    }

    /**
     * An OR in the existing clause keeps its meaning because both sides are grouped.
     */
    public function testConditionIsCombinedWithAPresentWhereClauseGrouped(): void
    {
        $query = new SqlQuery(
            new SqlFragment('`users`.`name`'),
            new SqlFragment('`users`'),
            new SqlFragment('a = :a OR b = :b', [':a' => 1, ':b' => 2]),
        );

        $derived = $query->andWhere(new SqlFragment('c = :c OR d = :d', [':c' => 3, ':d' => 4]));

        $this->assertSame('(a = :a OR b = :b) AND (c = :c OR d = :d)', $derived->getWhere()->getSql());
        $this->assertSame([':a' => 1, ':b' => 2, ':c' => 3, ':d' => 4], $derived->getWhere()->getParams());
    }

    public function testConditionBecomesAnAbsentHavingClauseUngrouped(): void
    {
        $query = new SqlQuery(
            new SqlFragment('`users`.`userId`, COUNT(*) AS total'),
            new SqlFragment('`users`'),
            new SqlFragment('`users`.`age` >= :age', [':age' => 21]),
            new SqlFragment('`users`.`userId`'),
        );

        $derived = $query->andHaving(new SqlFragment('`total` > :total', [':total' => 2]));

        $this->assertSame('`total` > :total', $derived->getHaving()->getSql());
        $this->assertSame([':total' => 2], $derived->getHaving()->getParams());
        $this->assertSame($query->getWhere(), $derived->getWhere());
    }

    public function testHavingIsCombinedLikeWhereAndLeavesWhereUntouched(): void
    {
        $query = $this->createQuery()->with(having: new SqlFragment('a = :a OR b = :b', [':a' => 1, ':b' => 2]));

        $derived = $query->andHaving(new SqlFragment('`total` > :total', [':total' => 2]));

        $this->assertSame('(a = :a OR b = :b) AND (`total` > :total)', $derived->getHaving()->getSql());
        $this->assertSame([':a' => 1, ':b' => 2, ':total' => 2], $derived->getHaving()->getParams());
        $this->assertSame($query->getWhere(), $derived->getWhere());
        $this->assertSame($query->getSelect(), $derived->getSelect());
        $this->assertSame($query->getFrom(), $derived->getFrom());
        $this->assertSame($query->getGroupBy(), $derived->getGroupBy());
    }

    public function testAndingLeavesTheOriginalQueryUnchanged(): void
    {
        $query = $this->createQuery();

        $derived = $query
            ->andWhere(new SqlFragment('`users`.`name` = :name', [':name' => 'Alice']))
            ->andHaving(new SqlFragment('`total` < :most', [':most' => 9]));

        $this->assertNotSame($query, $derived);
        $this->assertSame('`users`.`age` >= :age', $query->getWhere()->getSql());
        $this->assertSame([':age' => 21], $query->getWhere()->getParams());
        $this->assertSame('`total` > :total', $query->getHaving()->getSql());
        $this->assertSame([':total' => 2], $query->getHaving()->getParams());
    }

    public function testEmptyConditionLeavesAPresentClauseUnchanged(): void
    {
        $query = $this->createQuery();

        $derived = $query->andWhere(new SqlFragment(''))->andHaving(new SqlFragment(''));

        $this->assertEquals($query, $derived);
        $this->assertSame($query->getWhere(), $derived->getWhere());
        $this->assertSame($query->getHaving(), $derived->getHaving());
    }

    public function testEmptyConditionLeavesAnAbsentClauseAbsent(): void
    {
        $query = new SqlQuery(new SqlFragment('`users`.`name`'), new SqlFragment('`users`'));

        $derived = $query->andWhere(new SqlFragment(''))->andHaving(new SqlFragment(''));

        $this->assertEquals($query, $derived);
        $this->assertNull($derived->getWhere());
        $this->assertNull($derived->getHaving());
    }

    /**
     * Bindings with nowhere to bind are a contradiction, not an empty condition.
     */
    public function testEmptyConditionBindingParametersIsRejected(): void
    {
        $query = $this->createQuery();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Cannot AND a condition with empty SQL onto the WHERE clause while it binds parameters'
        );

        $query->andWhere(new SqlFragment('', [':age' => 21]));
    }

    public function testSharedParameterBoundToSameValueIsCarriedOnce(): void
    {
        $query = $this->createQuery();

        $derived = $query->andWhere(new SqlFragment('`users`.`age` != :age', [':age' => 21]));

        $this->assertSame('(`users`.`age` >= :age) AND (`users`.`age` != :age)', $derived->getWhere()->getSql());
        $this->assertSame([':age' => 21], $derived->getWhere()->getParams());
    }

    public function testSharedParameterBoundToDifferentValuesIsRejected(): void
    {
        $query = $this->createQuery();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Parameter ':age' is bound to different values by the WHERE clause and the condition ANDed onto it"
        );

        $query->andWhere(new SqlFragment('`users`.`age` < :age', [':age' => 65]));
    }

    public function testSharedHavingParameterBoundToDifferentValuesIsRejected(): void
    {
        $query = $this->createQuery();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Parameter ':total' is bound to different values by the HAVING clause and the condition ANDed onto it"
        );

        $query->andHaving(new SqlFragment('`total` < :total', [':total' => 9]));
    }

    /* -------------------------------
     * Query SQL and parameters are produced together
     * ----------------------------- */

    public function testRenderedSqlContainsEveryPresentClauseInOrder(): void
    {
        $rendered = $this->createQuery()->render();

        $this->assertSame(
            'SELECT `users`.`userId`, COUNT(*) AS total FROM `users`'
            . "\nWHERE `users`.`age` >= :age"
            . "\nGROUP BY `users`.`userId`"
            . "\nHAVING `total` > :total",
            $rendered->getSql()
        );
        $this->assertSame([':age' => 21, ':total' => 2], $rendered->getParams());
    }

    public function testAbsentClausesContributeNoKeyword(): void
    {
        $query = new SqlQuery(new SqlFragment('`users`.`name`'), new SqlFragment('`users`'));

        $sql = $query->render()->getSql();

        $this->assertSame('SELECT `users`.`name` FROM `users`', $sql);
        $this->assertStringNotContainsString('WHERE', $sql);
        $this->assertStringNotContainsString('GROUP BY', $sql);
        $this->assertStringNotContainsString('HAVING', $sql);
    }

    public function testEveryPlaceholderHasABinding(): void
    {
        $rendered = $this->createQuery()->render();

        preg_match_all('/:[a-zA-Z0-9_]+/', $rendered->getSql(), $matches);

        $this->assertCount(2, $matches[0]);
        foreach ($matches[0] as $placeholder) {
            $this->assertArrayHasKey($placeholder, $rendered->getParams());
        }
    }

    /**
     * Parameters of a removed clause do not survive into the rendered result.
     */
    public function testEveryBindingBelongsToARenderedClause(): void
    {
        $rendered = $this->createQuery()->with(having: SqlQuery::NONE)->render();

        $this->assertSame([':age' => 21], $rendered->getParams());
        $this->assertStringNotContainsString(':total', $rendered->getSql());

        preg_match_all('/:[a-zA-Z0-9_]+/', $rendered->getSql(), $matches);
        $this->assertSame(array_keys($rendered->getParams()), $matches[0]);
    }

    public function testCollidingParameterNamesAcrossClausesAreRejected(): void
    {
        $query = new SqlQuery(
            new SqlFragment('`users`.`name`'),
            new SqlFragment('`users`'),
            new SqlFragment('`users`.`age` = :value', [':value' => 21]),
            null,
            new SqlFragment('`total` = :value', [':value' => 99]),
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Parameter ':value' is bound to different values by the WHERE clause and the HAVING clause"
        );

        $query->render();
    }

    /**
     * Rebinding the same name to the same value discards nothing, so it is accepted.
     */
    public function testSameParameterBoundToSameValueIsAccepted(): void
    {
        $query = new SqlQuery(
            new SqlFragment('`users`.`name`'),
            new SqlFragment('`users`'),
            new SqlFragment('`users`.`age` = :value', [':value' => 21]),
            null,
            new SqlFragment('`total` = :value', [':value' => 21]),
        );

        $this->assertSame([':value' => 21], $query->render()->getParams());
    }
}
