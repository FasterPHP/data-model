<?php
/**
 * Tests for Data Model Item class.
 */
namespace FasterPhp\DataModel;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use FasterPhp\DataModel\Field;
use FasterPhp\DataModel\TestModel;

/**
 * Tests for Data Model Item class.
 */
class ItemTest extends TestCase
{
	protected array $_data = [
		'id' => 123,
		'name' => 'Marcus',
		'age' => 25,
		'height' => 6.25,
		'handsome' => true,
	];

	public function testSetViaConstructor(): void
	{
		$item = new TestModel\ValidItem($this->_data);

		$this->assertSame($this->_data['id'], $item->getId());
		$this->assertSame($this->_data['name'], $item->getName());
		$this->assertSame($this->_data['age'], $item->getAge());
		$this->assertSame($this->_data['height'], $item->getHeight());
		$this->assertSame($this->_data['handsome'], $item->getHandsome());
	}

	public function testSetViaSetters(): void
	{
		$item = new TestModel\ValidItem();
		$item->setId($this->_data['id']);
		$item->setName($this->_data['name']);
		$item->setAge($this->_data['age']);
		$item->setHeight($this->_data['height']);
		$item->setHandsome($this->_data['handsome']);

		$this->assertSame($this->_data['id'], $item->getId());
		$this->assertSame($this->_data['name'], $item->getName());
		$this->assertSame($this->_data['age'], $item->getAge());
		$this->assertSame($this->_data['height'], $item->getHeight());
		$this->assertSame($this->_data['handsome'], $item->getHandsome());
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
		$item = new TestModel\ValidItem($this->_data);
		$this->assertFalse($item->isDirty());

		$item->setName($this->_data['name']);
		$this->assertFalse($item->isDirty());
	}

	public function testIsDirtyTrue(): void
	{
		$item = new TestModel\ValidItem($this->_data);

		$item->setId(234);
		$this->assertTrue($item->isDirty());
	}

	public function testIsToDeleteFalse(): void
	{
		$item = new TestModel\ValidItem($this->_data);
		$this->assertFalse($item->isToDelete());
	}

	public function testIsToDeleteTrue(): void
	{
		$item = new TestModel\ValidItem($this->_data);
		$item->setToDelete();
		$this->assertTrue($item->isToDelete());
	}

	public function testLazyLoadFields(): void
	{
		$item = new TestModel\ValidItem($this->_data);

		$dataProperty = new ReflectionProperty($item, '_data');

		$this->assertSame($this->_data, $dataProperty->getValue($item));
		$this->assertSame($this->_data['name'], $item->getName());

		$data = $dataProperty->getValue($item);
		$this->assertInstanceOf(Field\Base::class, $data['name']);
		$this->assertSame($this->_data['id'], $data['id']);
		$this->assertSame($this->_data['age'],$data['age']);
		$this->assertSame($this->_data['height'], $data['height']);
		$this->assertSame($this->_data['handsome'], $data['handsome']);
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

	public function testSerializable(): void
	{
		$item = new TestModel\ValidItem($this->_data);

		$serialized = $item->serialize();
		$item->unserialize($serialized);

		$this->assertSame($this->_data['id'], $item->getId());
		$this->assertSame($this->_data['name'], $item->getName());
		$this->assertSame($this->_data['age'], $item->getAge());
		$this->assertSame($this->_data['height'], $item->getHeight());
		$this->assertFalse($item->isTemp());
		$this->assertFalse($item->isDirty());
	}

	public function testSerializeUnserialize(): void
	{
		$item = new TestModel\ValidItem($this->_data);

		$serialized = serialize($item);
		$unserialized = unserialize($serialized);

		$this->assertInstanceOf(TestModel\ValidItem::class, $unserialized);
		$this->assertSame($item->getValues(), $unserialized->getValues());
	}

	public function testToString(): void
	{
		$item = new TestModel\ValidItem($this->_data);
		$this->assertSame('{"id":123,"name":"Marcus","age":25,"height":6.25,"handsome":true}', strval($item));
	}


}
