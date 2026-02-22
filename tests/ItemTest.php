<?php

/**
 * Tests for Data Model Item class.
 */

namespace FasterPhp\DataModel;

use BadMethodCallException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
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
        $item = new TestModel\ValidItem($this->data, isTemp: false);

        $this->assertSame($this->data['id'], $item->getId());
        $this->assertSame($this->data['name'], $item->getName());
        $this->assertSame($this->data['age'], $item->getAge());
        $this->assertSame($this->data['height'], $item->getHeight());
        $this->assertSame($this->data['handsome'], $item->getHandsome());
    }

    public function testSetViaSetters(): void
    {
        $item = new TestModel\ValidItem();
        $item->setName($this->data['name']);
        $item->setAge($this->data['age']);
        $item->setHeight($this->data['height']);
        $item->setHandsome($this->data['handsome']);
        $item->markItemPersisted($this->data['id']);

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
        $item = new TestModel\ValidItem(['id' => 123], isTemp: false);
        $this->assertFalse($item->isTemp());
    }

    public function testIsTempTrue(): void
    {
        $item = new TestModel\ValidItem();
        $this->assertTrue($item->isTemp());
    }

    public function testIsDirtyFalse(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $this->assertFalse($item->isDirty());

        $item->setName($this->data['name']);
        $this->assertFalse($item->isDirty());
    }

    public function testIsDirtyTrue(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);

        $item->setName('NewName');
        $this->assertTrue($item->isDirty());
    }

    public function testIsToDeleteFalse(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $this->assertFalse($item->isToDelete());
    }

    public function testIsToDeleteTrue(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $item->setToDelete();
        $this->assertTrue($item->isToDelete());
    }

    public function testLazyLoadFields(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);

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
        $item = new TestModel\ValidItem($this->data, isTemp: false);

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
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $this->assertSame('{"id":123,"name":"Marcus","age":25,"height":6.25,"handsome":true}', strval($item));
    }

    public function testGetRawData(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $this->assertSame($this->data, $item->getRawData());
    }

    public function testGetChangedSqlValues(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $item->setName('NewName');
        $changed = $item->getChangedSqlValues();
        $this->assertArrayHasKey('name', $changed);
        $this->assertSame('NewName', $changed['name']);
        $this->assertArrayNotHasKey('age', $changed);
    }

    public function testHasFieldChanged(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $this->assertFalse($item->hasFieldChanged('name'));
        $item->setName('NewName');
        $this->assertTrue($item->hasFieldChanged('name'));
    }

    public function testJsonSerialize(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $expected = ['id' => $this->data['id']] + $item->getValues();
        $this->assertSame($expected, $item->jsonSerialize());
    }

    public function testBadMethodCall(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $this->expectException(BadMethodCallException::class);
        $item->doSomething();
    }

    public function testGetFieldNotDefined(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
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
        $item = new TestModel\ReadonlyItem(
            ['id' => 1, 'name' => 'Test', 'email' => 'a@b.com', 'createdAt' => '2025-01-01 00:00:00'],
            isTemp: false,
        );
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

    public function testFieldsWithoutValidatorMethodSkipped(): void
    {
        // ValidItem has 5 fields but only validateName() and validateAge() methods.
        // height, handsome, id should be silently skipped with no errors.
        $item = new TestModel\ValidItem();
        $item->setName('Donald Duck');
        $item->setAge(18);
        $this->assertTrue($item->isValid());
        $errors = $item->getValidationErrors();
        $this->assertArrayNotHasKey('height', $errors);
        $this->assertArrayNotHasKey('handsome', $errors);
        $this->assertArrayNotHasKey('id', $errors);
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

    public function testValidateWithNoValidatorMethods(): void
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

    // --- Implicit id field tests ---

    public function testImplicitIdFieldCreation(): void
    {
        $item = new TestModel\ValidItem();
        $this->assertNull($item->getId());

        $item = new TestModel\ValidItem(['id' => 42], isTemp: false);
        $this->assertSame(42, $item->getId());
    }

    public function testIdTypeOverride(): void
    {
        // ValidItem uses default ID_TYPE (Field\Integer)
        $this->assertSame(Field\Integer::class, TestModel\ValidItem::ID_TYPE);
    }

    public function testSetIdThrowsException(): void
    {
        $item = new TestModel\ValidItem();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('managed automatically');
        $item->setId(123);
    }

    public function testSetIdThrowsOnLoadedItem(): void
    {
        $item = new TestModel\ValidItem(['id' => 1], isTemp: false);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('managed automatically');
        $item->setId(456);
    }

    public function testIdInFieldsThrowsMigrationGuard(): void
    {
        // Create an anonymous class that still declares 'id' in FIELDS
        $itemClass = new class () extends \FasterPhp\DataModel\Item {
            public const ID_FIELD = 'userId';
            public const FIELDS = [
                'id' => Field\Integer::class,
                'name' => Field\Varchar::class,
            ];
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Do not declare');
        $this->expectExceptionMessage('ID_TYPE');
        $itemClass->getId();
    }

    public function testSerialisationMethodsIncludeId(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);

        // jsonSerialize includes id
        $json = $item->jsonSerialize();
        $this->assertArrayHasKey('id', $json);
        $this->assertSame(123, $json['id']);

        // __serialize includes id within 'values' key
        $serialized = $item->__serialize();
        $this->assertArrayHasKey('values', $serialized);
        $this->assertArrayHasKey('id', $serialized['values']);
        $this->assertSame(123, $serialized['values']['id']);

        // __toString includes id
        $string = (string) $item;
        $this->assertStringContainsString('"id":123', $string);
    }

    public function testGetValuesAndGetSqlValuesExcludeId(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);

        $values = $item->getValues();
        $this->assertArrayNotHasKey('id', $values);
        $this->assertArrayHasKey('name', $values);

        $sqlValues = $item->getSqlValues();
        $this->assertArrayNotHasKey('id', $sqlValues);
        $this->assertArrayHasKey('name', $sqlValues);
    }

    // --- Item state model tests ---

    public function testStateConstantsArePrivate(): void
    {
        $ref = new ReflectionClass(Item::class);
        foreach (['ITEM_STATE_TEMP', 'ITEM_STATE_CURRENT', 'ITEM_STATE_MODIFIED'] as $name) {
            $const = $ref->getReflectionConstant($name);
            $this->assertNotFalse($const, "Constant $name should exist");
            $this->assertTrue($const->isPrivate(), "Constant $name should be private");
        }
    }

    public function testStatePropertyIsPrivate(): void
    {
        $ref = new ReflectionClass(Item::class);
        $prop = $ref->getProperty('itemState');
        $this->assertTrue($prop->isPrivate());
    }

    public function testConstructorIsTempDefaultCreatesTemp(): void
    {
        $item = new TestModel\ValidItem();
        $this->assertTrue($item->isTemp());
        $this->assertFalse($item->isDirty());
    }

    public function testConstructorIsTempFalseCreatesCurrent(): void
    {
        $item = new TestModel\ValidItem(['id' => 5, 'name' => 'Alice'], isTemp: false);
        $this->assertFalse($item->isTemp());
        $this->assertFalse($item->isDirty());
    }

    public function testConstructorGuardTempWithIdThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('temporary item');
        new TestModel\ValidItem(['id' => 5]);
    }

    public function testConstructorGuardTempWithoutIdAllowed(): void
    {
        $item = new TestModel\ValidItem(['name' => 'Alice']);
        $this->assertTrue($item->isTemp());
    }

    public function testConstructorGuardNonTempWithIdAllowed(): void
    {
        $item = new TestModel\ValidItem(['id' => 5, 'name' => 'Alice'], isTemp: false);
        $this->assertSame(5, $item->getId());
    }

    public function testIsTempNewItem(): void
    {
        $item = new TestModel\ValidItem();
        $this->assertTrue($item->isTemp());
    }

    public function testIsTempLoadedItem(): void
    {
        $item = new TestModel\ValidItem(['id' => 1], isTemp: false);
        $this->assertFalse($item->isTemp());
    }

    public function testIsTempAfterMarkItemPersisted(): void
    {
        $item = new TestModel\ValidItem();
        $item->markItemPersisted(42);
        $this->assertFalse($item->isTemp());
    }

    public function testIsDirtyNewItem(): void
    {
        $item = new TestModel\ValidItem();
        $this->assertFalse($item->isDirty());
    }

    public function testIsDirtyLoadedItem(): void
    {
        $item = new TestModel\ValidItem(['id' => 1, 'name' => 'Alice'], isTemp: false);
        $this->assertFalse($item->isDirty());
    }

    public function testIsDirtyModifiedItem(): void
    {
        $item = new TestModel\ValidItem(['id' => 1, 'name' => 'Alice'], isTemp: false);
        $item->setName('Bob');
        $this->assertTrue($item->isDirty());
    }

    public function testIsDirtyTempItemWithSetValues(): void
    {
        $item = new TestModel\ValidItem();
        $item->setName('Bob');
        $this->assertFalse($item->isDirty());
    }

    public function testSetValueCurrentToModified(): void
    {
        $item = new TestModel\ValidItem(['id' => 1, 'name' => 'Alice'], isTemp: false);
        $item->setName('Bob');
        $this->assertTrue($item->isDirty());
    }

    public function testSetValueModifiedToCurrentOnRevert(): void
    {
        $item = new TestModel\ValidItem(['id' => 1, 'name' => 'Alice'], isTemp: false);
        $item->setName('Bob');
        $this->assertTrue($item->isDirty());
        $item->setName('Alice');
        $this->assertFalse($item->isDirty());
    }

    public function testSetValueTempStaysTemp(): void
    {
        $item = new TestModel\ValidItem();
        $item->setName('Bob');
        $this->assertTrue($item->isTemp());
        $this->assertFalse($item->isDirty());
    }

    public function testMarkItemPersistedTempWithId(): void
    {
        $item = new TestModel\ValidItem();
        $item->markItemPersisted(99);
        $this->assertSame(99, $item->getId());
        $this->assertFalse($item->isTemp());
        $this->assertFalse($item->isDirty());
    }

    public function testMarkItemPersistedTempWithoutId(): void
    {
        $item = new TestModel\ValidItem();
        $item->markItemPersisted();
        $this->assertFalse($item->isTemp());
    }

    public function testMarkItemPersistedModifiedItem(): void
    {
        $item = new TestModel\ValidItem(['id' => 1, 'name' => 'Alice'], isTemp: false);
        $item->setName('Bob');
        $this->assertTrue($item->isDirty());
        $item->markItemPersisted();
        $this->assertFalse($item->isDirty());
    }

    public function testMarkItemPersistedThrowsOnNonTempWithId(): void
    {
        $item = new TestModel\ValidItem(['id' => 1], isTemp: false);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('non-temporary');
        $item->markItemPersisted(2);
    }

    public function testMarkItemPersistedNotOnItemInterface(): void
    {
        $ref = new ReflectionClass(ItemInterface::class);
        $this->assertFalse($ref->hasMethod('markItemPersisted'));
    }

    public function testMarkItemPersistedReturnsVoid(): void
    {
        $ref = new ReflectionClass(TestModel\ValidItem::class);
        $method = $ref->getMethod('markItemPersisted');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('void', $returnType->getName());
    }

    public function testSerializeUnserializeCurrentItem(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $unserialized = unserialize(serialize($item));
        $this->assertFalse($unserialized->isTemp());
        $this->assertFalse($unserialized->isDirty());
        $this->assertFalse($unserialized->isToDelete());
    }

    public function testSerializeUnserializeModifiedItem(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $item->setName('Bob');
        $unserialized = unserialize(serialize($item));
        $this->assertTrue($unserialized->isDirty());
    }

    public function testSerializeUnserializeTempItem(): void
    {
        $item = new TestModel\ValidItem();
        $unserialized = unserialize(serialize($item));
        $this->assertTrue($unserialized->isTemp());
    }

    public function testSerializeUnserializeItemMarkedForDeletion(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $item->setToDelete();
        $unserialized = unserialize(serialize($item));
        $this->assertTrue($unserialized->isToDelete());
    }

    public function testToStringDelegatesToJsonSerialize(): void
    {
        $item = new TestModel\ValidItem($this->data, isTemp: false);
        $this->assertSame(json_encode($item->jsonSerialize()), (string) $item);
    }

    public function testClearOriginalValuesRemoved(): void
    {
        $ref = new ReflectionClass(ItemInterface::class);
        $this->assertFalse($ref->hasMethod('clearOriginalValues'));
    }
}
