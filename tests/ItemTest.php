<?php

/**
 * Tests for Data Model Item class.
 */

namespace FasterPhp\DataModel;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Tests for Data Model Item class.
 */
class ItemTest extends TestCase
{
    protected array $data = [
        'id' => 123,
        'name' => 'Marcus',
        'age' => 25,
        'height' => 6.25,
        'handsome' => true,
    ];

    public function testSetViaConstructor(): void
    {
        $item = new TestModel\ValidItem($this->data);

        $this->assertSame($this->data['id'], $item->getId());
        $this->assertSame($this->data['name'], $item->getName());
        $this->assertSame($this->data['age'], $item->getAge());
        $this->assertSame($this->data['height'], $item->getHeight());
        $this->assertSame($this->data['handsome'], $item->getHandsome());
    }

    public function testSetViaSetters(): void
    {
        $item = new TestModel\ValidItem();
        $item->setId($this->data['id']);
        $item->setName($this->data['name']);
        $item->setAge($this->data['age']);
        $item->setHeight($this->data['height']);
        $item->setHandsome($this->data['handsome']);

        $this->assertSame($this->data['id'], $item->getId());
        $this->assertSame($this->data['name'], $item->getName());
        $this->assertSame($this->data['age'], $item->getAge());
        $this->assertSame($this->data['height'], $item->getHeight());
        $this->assertSame($this->data['handsome'], $item->getHandsome());
    }

    public function testDefaults(): void
    {
        $item = new TestModel\ValidItem();
        $this->assertNull($item->getId());
        $this->assertSame(TestModel\ValidItem::DEFAULTS['name'], $item->getName());
        $this->assertSame(TestModel\ValidItem::DEFAULTS['age'], $item->getAge());
        $this->assertSame(TestModel\ValidItem::DEFAULTS['height'], $item->getHeight());
        $this->assertSame(TestModel\ValidItem::DEFAULTS['handsome'], $item->getHandsome());
    }

    public function testIsTempFalse(): void
    {
        $item = new TestModel\ValidItem(['id' => 123]);
        $this->assertFalse($item->isTemp());
    }

    public function testIsTempTrue(): void
    {
        $item = new TestModel\ValidItem();
        $this->assertTrue($item->isTemp());
    }

    public function testIsDirtyFalse(): void
    {
        $item = new TestModel\ValidItem($this->data);
        $this->assertFalse($item->isDirty());

        $item->setName($this->data['name']);
        $this->assertFalse($item->isDirty());
    }

    public function testIsDirtyTrue(): void
    {
        $item = new TestModel\ValidItem($this->data);

        $item->setId(234);
        $this->assertTrue($item->isDirty());
    }

    public function testIsToDeleteFalse(): void
    {
        $item = new TestModel\ValidItem($this->data);
        $this->assertFalse($item->isToDelete());
    }

    public function testIsToDeleteTrue(): void
    {
        $item = new TestModel\ValidItem($this->data);
        $item->setToDelete();
        $this->assertTrue($item->isToDelete());
    }

    public function testLazyLoadFields(): void
    {
        $item = new TestModel\ValidItem($this->data);

        $dataProperty = new ReflectionProperty($item, 'data');

        $this->assertSame($this->data, $dataProperty->getValue($item));
        $this->assertSame($this->data['name'], $item->getName());

        $data = $dataProperty->getValue($item);
        $this->assertInstanceOf(Field\Base::class, $data['name']);
        $this->assertSame($this->data['id'], $data['id']);
        $this->assertSame($this->data['age'], $data['age']);
        $this->assertSame($this->data['height'], $data['height']);
        $this->assertSame($this->data['handsome'], $data['handsome']);
    }

    public function testValidateErrorsNotValidated(): void
    {
        $this->expectException(\FasterPhp\DataModel\Exception::class);
        $this->expectExceptionMessage('Item not validated');

        $item = new TestModel\ValidItem();
        $item->getValidationErrors();
    }

    public function testValidateFalse(): void
    {
        $item = new TestModel\ValidItem();
        $item->setName('2');
        $item->setAge(17);

        $this->assertFalse($item->isValid());
        $this->assertSame([
            'name' => [
                'Name must be between 2 and 60 characters',
                'Name cannot contain numbers',
            ],
            'age' => [
                'You must be at least 18 to use this app',
            ]
        ], $item->getValidationErrors());
    }

    public function testValidateTrue(): void
    {
        $item = new TestModel\ValidItem();
        $item->setName('Donald Duck');
        $item->setAge(18);

        $this->assertTrue($item->isValid());
        $this->assertSame([], $item->getValidationErrors());
    }

    public function testSerializeUnserialize(): void
    {
        $item = new TestModel\ValidItem($this->data);

        $serialized = serialize($item);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(TestModel\ValidItem::class, $unserialized);
        $this->assertSame($this->data['id'], $unserialized->getId());
        $this->assertSame($this->data['name'], $unserialized->getName());
        $this->assertSame($this->data['age'], $unserialized->getAge());
        $this->assertSame($this->data['height'], $unserialized->getHeight());
        $this->assertFalse($unserialized->isTemp());
        $this->assertFalse($unserialized->isDirty());
        $this->assertSame($item->getValues(), $unserialized->getValues());
        $this->assertEquals($unserialized, $item);
    }

    public function testToString(): void
    {
        $item = new TestModel\ValidItem($this->data);
        $this->assertSame('{"id":123,"name":"Marcus","age":25,"height":6.25,"handsome":true}', strval($item));
    }

    public function testGetRawData(): void
    {
        $item = new TestModel\ValidItem($this->data);
        $this->assertSame($this->data, $item->getRawData());
    }

    public function testGetChangedSqlValues(): void
    {
        $item = new TestModel\ValidItem($this->data);
        $item->setName('NewName');
        $changed = $item->getChangedSqlValues();
        $this->assertArrayHasKey('name', $changed);
        $this->assertSame('NewName', $changed['name']);
        $this->assertArrayNotHasKey('age', $changed);
    }

    public function testHasFieldChanged(): void
    {
        $item = new TestModel\ValidItem($this->data);
        $this->assertFalse($item->hasFieldChanged('name'));
        $item->setName('NewName');
        $this->assertTrue($item->hasFieldChanged('name'));
    }

    public function testJsonSerialize(): void
    {
        $item = new TestModel\ValidItem($this->data);
        $this->assertSame($item->getValues(), $item->jsonSerialize());
    }

    public function testBadMethodCall(): void
    {
        $item = new TestModel\ValidItem($this->data);
        $this->expectException(BadMethodCallException::class);
        $item->doSomething();
    }

    public function testGetFieldNotDefined(): void
    {
        $item = new TestModel\ValidItem($this->data);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Field 'nonexistent' not defined");
        $item->getNonexistent();
    }

    public function testCreateItemOnSet(): void
    {
        $set = new TestModel\ValidSet();
        $this->assertCount(0, $set);
        $item = $set->createItem();
        $this->assertInstanceOf(TestModel\ValidItem::class, $item);
        $this->assertCount(1, $set);
    }

    public function testSetReadonlyFieldThrowsOnSet(): void
    {
        $item = new TestModel\ReadonlyItem(['id' => 1, 'name' => 'Test', 'email' => 'a@b.com', 'createdAt' => '2025-01-01 00:00:00']);
        // Getting a readonly field value should work
        $this->assertNotNull($item->getCreatedAt());

        // Setting a readonly field via Item::setValue should throw
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Cannot update value for read-only field 'createdAt'");
        $item->setCreatedAt('2025-06-01 00:00:00');
    }

    public function testUtilGetRepositoryClassName(): void
    {
        $this->assertSame(
            'FasterPhp\DataModel\TestModel\ValidRepository',
            Util::getRepositoryClassName('FasterPhp\DataModel\TestModel\ValidItem')
        );
    }

    public function testValidatorMissingClass(): void
    {
        $item = new TestModel\MissingClassItem();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Validator class name missing for field 'noclass'");
        $item->isValid();
    }

    public function testValidatorSkipIfEmpty(): void
    {
        $item = new TestModel\SkipIfEmptyItem();
        // name is empty (default ''), skipIfEmpty=true should skip validation
        $this->assertTrue($item->isValid());
    }

    public function testValidatorSkipIfEmptyNotSkipped(): void
    {
        $item = new TestModel\SkipIfEmptyItem();
        $item->setName('x');
        // name is not empty, validator runs — 'x' is too short (min 3)
        $this->assertFalse($item->isValid());
    }

    public function testValidateWithNoValidatorsConst(): void
    {
        $item = new TestModel\NoValidatorsItem();
        $item->setName('anything');
        $this->assertTrue($item->isValid());
        $this->assertSame([], $item->getValidationErrors());
    }

    public function testCallbackValidatorPass(): void
    {
        $item = new TestModel\CallbackValidatorItem();
        $item->setName('Hello');
        $this->assertTrue($item->isValid());
    }

    public function testCallbackValidatorFail(): void
    {
        $item = new TestModel\CallbackValidatorItem();
        $item->setName('H');
        $this->assertFalse($item->isValid());
        $errors = $item->getValidationErrors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertSame('Name is invalid', $errors['name'][0]);
    }
}
