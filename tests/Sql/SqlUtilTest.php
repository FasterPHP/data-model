<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\Sql;

use FasterPhp\DataModel\Exception;
use FasterPhp\DataModel\Repository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlUtilTest extends TestCase
{
    public function testIdentSimple(): void
    {
        $this->assertEquals('`users`', SqlUtil::ident('users'));
    }

    public function testIdentWithDot(): void
    {
        $this->assertEquals('`users`.`userId`', SqlUtil::ident('users.userId'));
    }

    public function testIdentWithMultipleDots(): void
    {
        $this->assertEquals('`db`.`users`.`userId`', SqlUtil::ident('db.users.userId'));
    }

    public function testIdentAcceptsPlainIdentifier(): void
    {
        $this->assertSame('`courseId`', SqlUtil::ident('courseId'));
    }

    public function testIdentAcceptsDotQualifiedIdentifier(): void
    {
        $this->assertSame('`a`.`courseId`', SqlUtil::ident('a.courseId'));
        $this->assertSame('`users`.`userId`', SqlUtil::ident('users.userId'));
    }

    #[DataProvider('invalidIdentifierProvider')]
    public function testIdentRejectsInvalidIdentifier(string $name): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid SQL identifier '$name'");
        SqlUtil::ident($name);
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function invalidIdentifierProvider(): array
    {
        return [
            'empty string' => [''],
            'leading dot' => ['.userId'],
            'trailing dot' => ['users.'],
            'doubled dot' => ['users..userId'],
            'space' => ['user id'],
            'expression' => ['COUNT(*)'],
            'comma' => ['userId, name'],
            'quotation mark' => ['user"id'],
            'backtick' => ['user`id'],
            'injection attempt' => ['id` = 1 OR `1'],
        ];
    }

    public function testIsValidIdent(): void
    {
        $this->assertTrue(SqlUtil::isValidIdent('userId'));
        $this->assertTrue(SqlUtil::isValidIdent('a.courseId'));
        $this->assertTrue(SqlUtil::isValidIdent('db.users.userId'));
        $this->assertFalse(SqlUtil::isValidIdent(''));
        $this->assertFalse(SqlUtil::isValidIdent('COUNT(*)'));
    }

    /**
     * Backtick doubling is unreachable through ident() now that the shape rule excludes
     * backticks, so the secondary control is exercised directly.
     */
    public function testQuoteSegmentDoublesEmbeddedBacktick(): void
    {
        $method = (new \ReflectionClass(SqlUtil::class))->getMethod('quoteSegment');
        $method->setAccessible(true);

        $this->assertSame('`user``id`', $method->invoke(null, 'user`id'));
        $this->assertSame('`id`` = 1 OR ``1`', $method->invoke(null, 'id` = 1 OR `1'));
    }

    /**
     * Keys differing only in characters invalid in a placeholder name do not collide.
     */
    public function testPlaceholderIsInjective(): void
    {
        $placeholders = [
            SqlUtil::placeholder('user.id'),
            SqlUtil::placeholder('user_id'),
            SqlUtil::placeholder('user-id'),
        ];

        $this->assertCount(3, array_unique($placeholders));

        // A key that is already a valid placeholder name is left unchanged.
        $this->assertSame(':user_id', $placeholders[1]);
    }

    public function testPlaceholderSimple(): void
    {
        $this->assertEquals(':userId', SqlUtil::placeholder('userId'));
    }

    public function testPlaceholderWithSpecialChars(): void
    {
        $this->assertEquals(':user_id_f01b6fab', SqlUtil::placeholder('user-id'));
        $this->assertEquals(':user_name_e010fbb0', SqlUtil::placeholder('user.name'));
        $this->assertEquals(':user_email_f7d03762', SqlUtil::placeholder('user@email'));
    }

    public function testPlaceholderWithMixedChars(): void
    {
        $this->assertEquals(':abc123_def_f1a48cc4', SqlUtil::placeholder('abc123-def'));
    }

    /**
     * The Repository constants alias the canonical values on SqlUtil, so consumers writing
     * BaseRepository::CONTAINS still get the same string.
     */
    public function testSearchTypeConstantsAreUnchanged(): void
    {
        $this->assertSame('starts', SqlUtil::STARTS);
        $this->assertSame('ends', SqlUtil::ENDS);
        $this->assertSame('contains', SqlUtil::CONTAINS);

        $this->assertSame('starts', Repository::STARTS);
        $this->assertSame('ends', Repository::ENDS);
        $this->assertSame('contains', Repository::CONTAINS);
    }

    public function testLikeWildcardsStarts(): void
    {
        $this->assertEquals('test%', SqlUtil::likeWildcards('test', Repository::STARTS));
    }

    public function testLikeWildcardsEnds(): void
    {
        $this->assertEquals('%test', SqlUtil::likeWildcards('test', Repository::ENDS));
    }

    public function testLikeWildcardsContains(): void
    {
        $this->assertEquals('%test%', SqlUtil::likeWildcards('test', Repository::CONTAINS));
    }

    public function testLikeWildcardsDefault(): void
    {
        // Default case (no wildcards added for unknown search types)
        $this->assertEquals('test', SqlUtil::likeWildcards('test', 'unknown'));
        $this->assertEquals('test', SqlUtil::likeWildcards('test', 'exact'));
    }

    public function testExpandInWithValues(): void
    {
        [$sql, $params] = SqlUtil::expandIn('userId', [1, 2, 3]);

        $this->assertEquals('userId IN (:p0,:p1,:p2)', $sql);
        $this->assertEquals([':p0' => 1, ':p1' => 2, ':p2' => 3], $params);
    }

    public function testExpandInWithCustomPrefix(): void
    {
        [$sql, $params] = SqlUtil::expandIn('userId', [10, 20], 'user');

        $this->assertEquals('userId IN (:user0,:user1)', $sql);
        $this->assertEquals([':user0' => 10, ':user1' => 20], $params);
    }

    public function testExpandInWithSingleValue(): void
    {
        [$sql, $params] = SqlUtil::expandIn('userId', [42]);

        $this->assertEquals('userId IN (:p0)', $sql);
        $this->assertEquals([':p0' => 42], $params);
    }

    public function testExpandInWithEmptyArray(): void
    {
        [$sql, $params] = SqlUtil::expandIn('userId', []);

        $this->assertEquals('1 = 0', $sql);
        $this->assertEquals([], $params);
    }

    public function testExpandInWithStringValues(): void
    {
        [$sql, $params] = SqlUtil::expandIn('name', ['Alice', 'Bob']);

        $this->assertEquals('name IN (:p0,:p1)', $sql);
        $this->assertEquals([':p0' => 'Alice', ':p1' => 'Bob'], $params);
    }

    public function testExpandInWithMixedTypes(): void
    {
        [$sql, $params] = SqlUtil::expandIn('value', [1, 'test', null]);

        $this->assertEquals('value IN (:p0,:p1,:p2)', $sql);
        $this->assertEquals([':p0' => 1, ':p1' => 'test', ':p2' => null], $params);
    }

    public function testExpandInWithColumnExpression(): void
    {
        [$sql, $params] = SqlUtil::expandIn('`users`.`userId`', [1, 2]);

        $this->assertEquals('`users`.`userId` IN (:p0,:p1)', $sql);
        $this->assertEquals([':p0' => 1, ':p1' => 2], $params);
    }
}
