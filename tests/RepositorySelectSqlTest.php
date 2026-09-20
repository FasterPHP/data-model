<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\TestModel\JoinedRepository;
use FasterPhp\DataModel\TestModel\ParticipantRepository;
use FasterPhp\DataModel\TestModel\ValidRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Characterisation tests pinning the exact SELECT statement a repository generates.
 *
 * These exist so the introduction of the query hook can be shown to leave the generated SQL
 * byte-for-byte unchanged, both for a repository that overrides nothing and for one shaped like
 * a typical joined repository, which overrides getSelectClause() and getFromClause()
 * together.
 */
class RepositorySelectSqlTest extends TestCase
{
    private const PLAIN_SELECT = 'SELECT `users`.`userId` AS `id`, `users`.`name`, `users`.`age`'
        . ', `users`.`height`, `users`.`handsome`'
        . ' FROM `users`';

    private const JOINED_SELECT = 'SELECT `moduleAttempts`.`moduleAttemptId` AS `id`'
        . ', `moduleAttempts`.`moduleId`, `moduleAttempts`.`courseAttemptId`'
        . ', `moduleAttempts`.`currentTime`, `moduleAttempts`.`markedByUserId`,'
        . "\n\t\t\t\tROUND(100 * moduleAttempts.currentTime / m.duration) AS percentWatched,"
        . "\n\t\t\t\tm.name AS moduleName,"
        . "\n\t\t\t\tCONCAT(TRIM(mu.firstName), ' ', TRIM(mu.lastName)) AS markedByUserName"
        . " FROM moduleAttempts"
        . "\n\t\t\tJOIN modules m ON m.moduleId = moduleAttempts.moduleId"
        . "\n\t\t\tJOIN courseAttempts ca ON ca.courseAttemptId = moduleAttempts.courseAttemptId"
        . "\n\t\t\tLEFT JOIN users mu ON mu.userId = moduleAttempts.markedByUserId";

    private const PARTICIPANT_SELECT = 'SELECT `users`.`userId` AS `id`, `users`.`role`'
        . ', `users`.`status`,'
        . "\n\t\t\t\tCASE"
        . "\n\t\t\t\t\tWHEN a.status IS NULL THEN 'not started'"
        . "\n\t\t\t\t\tELSE a.status"
        . "\n\t\t\t\tEND AS courseStatus"
        . " FROM users"
        . "\n\t\t\tLEFT JOIN courseAttempts a ON a.userId = users.userId"
        . "\n\t\t\tLEFT JOIN courseAttempts a2 ON a2.userId = users.userId"
        . "\n\t\t\t\tAND a2.courseId = a.courseId"
        . "\n\t\t\t\tAND a2.courseAttemptId > a.courseAttemptId";

    /**
     * Build the SELECT statement and its parameters for a repository.
     *
     * @param array<string, mixed>  $params Filters to apply.
     * @param array<string, string> $types  Search type per filter key.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildSelect(Repository $repo, array $params, array $types = []): array
    {
        $method = (new \ReflectionClass($repo))->getMethod('buildSelectQuery');
        $method->setAccessible(true);
        $rendered = $method->invoke($repo, $params, $types)->render();
        return [$rendered->getSql(), $rendered->getParams()];
    }

    private function createPdo(): PDO
    {
        return new PDO('sqlite::memory:');
    }

    public function testPlainRepositoryWithNoFilters(): void
    {
        [$sql, $params] = $this->buildSelect(new ValidRepository($this->createPdo()), []);

        $this->assertSame(self::PLAIN_SELECT, $sql);
        $this->assertSame([], $params);
    }

    public function testPlainRepositoryWithAFilter(): void
    {
        [$sql, $params] = $this->buildSelect(
            new ValidRepository($this->createPdo()),
            ['age' => 21],
            ['age' => Repository::GREATER_OR_EQUALS]
        );

        $this->assertSame(self::PLAIN_SELECT . "\nWHERE `users`.`age` >= :age", $sql);
        $this->assertSame([':age' => '21'], $params);
    }

    public function testJoinedRepositoryWithNoFilters(): void
    {
        [$sql, $params] = $this->buildSelect(new JoinedRepository($this->createPdo()), []);

        $this->assertSame(self::JOINED_SELECT, $sql);
        $this->assertSame([], $params);
    }

    /**
     * A repository overriding the clause hooks and getWhereSqlAndParams() together, as
     * a course participant repository might, still injects its own filters.
     */
    public function testJoinedRepositoryInjectingItsOwnFilters(): void
    {
        [$sql, $params] = $this->buildSelect(new ParticipantRepository($this->createPdo()), []);

        $this->assertSame(
            self::PARTICIPANT_SELECT
            . "\nWHERE `users`.`role` = :role AND `users`.`status` = :status"
            . ' AND `a2`.`courseAttemptId` IS NULL',
            $sql
        );
        $this->assertSame([':role' => 'staff', ':status' => 'active'], $params);
    }

    /**
     * A caller's own filters are applied alongside the injected ones.
     */
    public function testJoinedRepositoryCombinesCallerAndInjectedFilters(): void
    {
        [$sql, $params] = $this->buildSelect(
            new ParticipantRepository($this->createPdo()),
            ['users.userId' => 5]
        );

        $this->assertStringContainsString('`users`.`userId` = :users_userId_214e18c3', $sql);
        $this->assertStringContainsString('`users`.`role` = :role', $sql);
        $this->assertSame(
            [':users_userId_214e18c3' => '5', ':role' => 'staff', ':status' => 'active'],
            $params
        );
    }

    public function testJoinedRepositoryWithAFilter(): void
    {
        [$sql, $params] = $this->buildSelect(
            new JoinedRepository($this->createPdo()),
            ['moduleId' => 7]
        );

        $this->assertSame(
            self::JOINED_SELECT . "\nWHERE `moduleAttempts`.`moduleId` = :moduleId",
            $sql
        );
        $this->assertSame([':moduleId' => '7'], $params);
    }
}
