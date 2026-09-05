<?php

declare(strict_types=1);

namespace FasterPhp\DataModel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SqlTest extends TestCase
{
    public function testIdentSimple(): void
    {
        $this->assertEquals('`users`', Sql::ident('users'));
    }

    public function testIdentWithDot(): void
    {
        $this->assertEquals('`users`.`userId`', Sql::ident('users.userId'));
    }

    public function testIdentWithMultipleDots(): void
    {
        $this->assertEquals('`db`.`users`.`userId`', Sql::ident('db.users.userId'));
    }

    public function testIdentAcceptsPlainIdentifier(): void
    {
        $this->assertSame('`courseId`', Sql::ident('courseId'));
    }

    public function testIdentAcceptsDotQualifiedIdentifier(): void
    {
        $this->assertSame('`a`.`courseId`', Sql::ident('a.courseId'));
        $this->assertSame('`users`.`userId`', Sql::ident('users.userId'));
    }

    #[DataProvider('invalidIdentifierProvider')]
    public function testIdentRejectsInvalidIdentifier(string $name): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid SQL identifier '$name'");
        Sql::ident($name);
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
        $this->assertTrue(Sql::isValidIdent('userId'));
        $this->assertTrue(Sql::isValidIdent('a.courseId'));
        $this->assertTrue(Sql::isValidIdent('db.users.userId'));
        $this->assertFalse(Sql::isValidIdent(''));
        $this->assertFalse(Sql::isValidIdent('COUNT(*)'));
    }

    /**
     * Backtick doubling is unreachable through ident() now that the shape rule excludes
     * backticks, so the secondary control is exercised directly.
     */
    public function testQuoteSegmentDoublesEmbeddedBacktick(): void
    {
        $method = (new \ReflectionClass(Sql::class))->getMethod('quoteSegment');
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
            Sql::placeholder('user.id'),
            Sql::placeholder('user_id'),
            Sql::placeholder('user-id'),
        ];

        $this->assertCount(3, array_unique($placeholders));

        // A key that is already a valid placeholder name is left unchanged.
        $this->assertSame(':user_id', $placeholders[1]);
    }

    public function testPlaceholderSimple(): void
    {
        $this->assertEquals(':userId', Sql::placeholder('userId'));
    }

    public function testPlaceholderWithSpecialChars(): void
    {
        $this->assertEquals(':user_id_f01b6fab', Sql::placeholder('user-id'));
        $this->assertEquals(':user_name_e010fbb0', Sql::placeholder('user.name'));
        $this->assertEquals(':user_email_f7d03762', Sql::placeholder('user@email'));
    }

    public function testPlaceholderWithMixedChars(): void
    {
        $this->assertEquals(':abc123_def_f1a48cc4', Sql::placeholder('abc123-def'));
    }

    public function testLikeWildcardsStarts(): void
    {
        $this->assertEquals('test%', Sql::likeWildcards('test', Repository::STARTS));
    }

    public function testLikeWildcardsEnds(): void
    {
        $this->assertEquals('%test', Sql::likeWildcards('test', Repository::ENDS));
    }

    public function testLikeWildcardsContains(): void
    {
        $this->assertEquals('%test%', Sql::likeWildcards('test', Repository::CONTAINS));
    }

    public function testLikeWildcardsDefault(): void
    {
        // Default case (no wildcards added for unknown search types)
        $this->assertEquals('test', Sql::likeWildcards('test', 'unknown'));
        $this->assertEquals('test', Sql::likeWildcards('test', 'exact'));
    }

    public function testExpandInWithValues(): void
    {
        [$sql, $params] = Sql::expandIn('userId', [1, 2, 3]);

        $this->assertEquals('userId IN (:p0,:p1,:p2)', $sql);
        $this->assertEquals([':p0' => 1, ':p1' => 2, ':p2' => 3], $params);
    }

    public function testExpandInWithCustomPrefix(): void
    {
        [$sql, $params] = Sql::expandIn('userId', [10, 20], 'user');

        $this->assertEquals('userId IN (:user0,:user1)', $sql);
        $this->assertEquals([':user0' => 10, ':user1' => 20], $params);
    }

    public function testExpandInWithSingleValue(): void
    {
        [$sql, $params] = Sql::expandIn('userId', [42]);

        $this->assertEquals('userId IN (:p0)', $sql);
        $this->assertEquals([':p0' => 42], $params);
    }

    public function testExpandInWithEmptyArray(): void
    {
        [$sql, $params] = Sql::expandIn('userId', []);

        $this->assertEquals('1 = 0', $sql);
        $this->assertEquals([], $params);
    }

    public function testExpandInWithStringValues(): void
    {
        [$sql, $params] = Sql::expandIn('name', ['Alice', 'Bob']);

        $this->assertEquals('name IN (:p0,:p1)', $sql);
        $this->assertEquals([':p0' => 'Alice', ':p1' => 'Bob'], $params);
    }

    public function testExpandInWithMixedTypes(): void
    {
        [$sql, $params] = Sql::expandIn('value', [1, 'test', null]);

        $this->assertEquals('value IN (:p0,:p1,:p2)', $sql);
        $this->assertEquals([':p0' => 1, ':p1' => 'test', ':p2' => null], $params);
    }

    public function testExpandInWithColumnExpression(): void
    {
        [$sql, $params] = Sql::expandIn('`users`.`userId`', [1, 2]);

        $this->assertEquals('`users`.`userId` IN (:p0,:p1)', $sql);
        $this->assertEquals([':p0' => 1, ':p1' => 2], $params);
    }
}
