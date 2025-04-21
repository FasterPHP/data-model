<?php

/**
 * Unit‑tests for the individual Field classes.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel;

use FasterPhp\DataModel\Field;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use DateTime;

class FieldTest extends TestCase
{
    /*
     * Base class
     */
    public function testGetName(): void
    {
        $f = (new Field\Integer('age'));
        $this->assertSame('age', $f->getName());
    }

    public function testIssetFalse(): void
    {
        $f = (new Field\Integer('age'));
        $this->assertFalse($f->isset());
    }

    public function testIssetTrue(): void
    {
        $f = (new Field\Integer('age'))->setValue(42);
        $this->assertTrue($f->isset());
    }

    /*
     * Integer
     */
    public function testIntegerNull(): void
    {
        $f = (new Field\Integer('age'))->setValue(null);
        $this->assertNull($f->getValue());
    }

    public function testIntegerValid(): void
    {
        $f = (new Field\Integer('age'))->setValue('42');
        $this->assertSame(42, $f->getValue());
        $this->assertSame(42, $f->getSqlValue());
        $this->assertSame('42', (string) $f);
    }

    public function testIntegerInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Field\Integer('age'))->setValue('abc');
    }

    /*
     * Double / Decimal
     */
    public function testDoubleNull(): void
    {
        $f = (new Field\Double('height'))->setValue(null);
        $this->assertNull($f->getValue());
    }

    public function testDoubleValid(): void
    {
        $f = (new Field\Double('height'))->setValue('5.75');
        $this->assertSame(5.75, $f->getValue());
        $this->assertSame(5.75, $f->getSqlValue());
    }

    public function testDoubleInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Field\Double('height'))->setValue('tall');
    }

    public function testDecimalAlias(): void
    {
        // Decimal extends Double, behaviour identical
        $f = (new Field\Decimal('price'))->setValue(19.99);
        $this->assertSame(19.99, $f->getValue());
    }

    /*
     * Boolean
     */
    public function testBooleanNull(): void
    {
        $f = (new Field\Boolean('active'))->setValue(null);
        $this->assertNull($f->getValue());
    }

    #[DataProvider('booleanProvider')]
    public function testBooleanConversions(string|bool|null $input = null, ?bool $expectedBool = null, ?string $expectedSql = null): void
    {
        $f = (new Field\Boolean('active'))->setValue($input);
        $this->assertSame($expectedBool, $f->getValue());
        $this->assertSame($expectedSql, $f->getSqlValue());
    }

    public static function booleanProvider(): array
    {
        return [
            [true,  true,  'y'],
            ['y',   true,  'y'],
            [1,     true,  'y'],
            ['1',   true,  'y'],
            [false, false, 'n'],
            ['n',   false, 'n'],
            [0,     false, 'n'],
            ['0',   false, 'n'],
            [null,  null,  null],
        ];
    }

    public function testBooleanInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Field\Boolean('active'))->setValue('yes please');
    }

    /*
     * Varchar / Char / Text / Enum  (all extend Varchar)
     */
    public function testVarcharNull(): void
    {
        $f = (new Field\Varchar('name'))->setValue(null);
        $this->assertNull($f->getValue());
    }

    public function testVarcharAcceptsScalarAndCasts(): void
    {
        $f = (new Field\Varchar('name'))->setValue(123);
        $this->assertSame('123', $f->getValue());
        $this->assertSame('123', $f->getSqlValue());
    }

    public function testVarcharArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $f = (new Field\Varchar('name'))->setValue([]);
    }

    public function testNonStringableObject(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $f = (new Field\Varchar('name'))->setValue(new \stdClass);
    }

    /*
     * Datetime
     */
    public function testDatetimeNull(): void
    {
        $f = (new Field\Datetime('created_at'))->setValue(null);
        $this->assertNull($f->getValue());
    }

    public function testDatetimeFromObject(): void
    {
        $dt = new DateTime('2025-04-21 15:30:00');
        $f  = (new Field\Datetime('created_at'))->setValue($dt);

        $this->assertSame($dt, $f->getValue());
        $this->assertSame('2025-04-21 15:30:00', $f->getSqlValue());
        $this->assertSame('21/04/2025', (string) $f);
    }

    public function testDatetimeFromString(): void
    {
        $f = (new Field\Datetime('created_at'))->setValue('2025-04-21 15:30:00');
        $this->assertInstanceOf(DateTime::class, $f->getValue());
        $this->assertSame('2025-04-21 15:30:00', $f->getSqlValue());
    }

    public function testDatetimeInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Field\Datetime('created_at'))->setValue(['not a date']);
    }

    /*
     * JSON
     */
    public function testJsonNull(): void
    {
        $f = (new Field\Json('meta'))->setValue(null);
        $this->assertNull($f->getValue());
    }

    public function testJsonDecode(): void
    {
        $f = (new Field\Json('meta'))->setValue('{"a":1}');
        $this->assertSame(['a' => 1], $f->getValue());
        $this->assertSame(['a' => 1], $f->getSqlValue());
    }

    public function testJsonEncode(): void
    {
        $f = (new Field\Json('meta'))->setValue(['b' => 2]);
        $this->assertSame('{"b":2}', $f->getValue());
        $this->assertSame('{"b":2}', $f->getSqlValue());
    }

    public function testJsonInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Field\Json('meta'))->setValue('"broken":}');
    }

    public function testJsonValueInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Field\Json('meta'))->setValue(INF);
    }
}
