<?php
/**
 * Data Model Item class.
 */
declare(strict_types=1);

namespace FasterPhp\DataModel;

use BadMethodCallException;
use Laminas\Validator\ValidatorChain;
use Serializable;
use Stringable;
use FasterPhp\DataModel\Field;

/**
 * Data Model Item class.
 */
abstract class Item implements Serializable, Stringable
{
	public const ID_FIELD = '';
	public const ID_INTERNAL = 'id';
	public const FIELDS = [];
	public const FIELDS_READONLY = [];
	public const FIELDS_EXTERNAL = [];
	public const DEFAULTS = [];
	public const VALIDATORS = [];

	protected array $_data;
	protected array $_originalValues = [];
	protected bool $_toDelete = false;
	protected bool $_isValid;
	protected array $_validationErrors;

	public function __construct(array $data = [])
	{
		$this->_data = $data;
	}

	public function getRawData(): array
	{
		return $this->_data;
	}

	public function getValues(): array
	{
		$values = [];
		foreach (array_keys(static::FIELDS) as $fieldName) {
			$values[$fieldName] = $this->_getField($fieldName)->getValue();
		}
		return $values;
	}

	public function getSqlValues(): array
	{
		$values = [];
		foreach (array_keys(static::FIELDS) as $fieldName) {
			$values[$fieldName] = $this->_getField($fieldName)->getSqlValue();
		}
		return $values;
	}

	public function isTemp(): bool
	{
		if (empty($this->_data[static::ID_INTERNAL])) {
			return true;
		} elseif (array_key_exists(static::ID_INTERNAL, $this->_originalValues)
			&& is_null($this->_originalValues[static::ID_INTERNAL])
		) {
			return true;
		}
		return false;
	}

	public function isDirty(): bool
	{
		return !empty($this->_originalValues);
	}

	public function setToDelete(bool $toDelete = true): static
	{
		$this->_toDelete = $toDelete;
		return $this;
	}

	public function isToDelete(): bool
	{
		return $this->_toDelete;
	}

	public function getChangedSqlValues(): array
	{
		$sqlValues = [];
		foreach (array_keys($this->_originalValues) as $fieldName) {
			$sqlValues[$fieldName] = $this->_getField($fieldName)->getSqlValue();
		}
		return $sqlValues;
	}

	public function clearOriginalValues(): static
	{
		$this->_originalValues = [];
		return $this;
	}

	public function isValid(): bool
	{
		if (!isset($this->_isValid)) {
			$this->validate();
		}
		return $this->_isValid;
	}

	public function validate(): void
	{
		//echo "validating " . get_called_class() . "\n";
		$this->_isValid = true;
		$this->_validationErrors = [];
		foreach (static::VALIDATORS as $fieldName => $validators) {
			//echo " - processing field '$fieldName' (" . count($validators) . " validators)\n";
			$validatorChain = new ValidatorChain();
			foreach ($validators as $args) {
				if (!isset($args['class'])) {
					throw new Exception("Validator class name missing for field '$fieldName'");
				}
				if (isset($args['skipIfEmpty'])
					&& true === $args['skipIfEmpty']
					&& empty($this->_getField($fieldName)->getValue())
				) {
					echo "skipping empty value for field '$fieldName'\n";
					continue;
				}
				$validator = new $args['class']($args['options'] ?? null);
				if (isset($args['message'])) {
					$validator->setMessage($args['message']);
				}
				$validatorChain->attach(
					$validator,
					breakChainOnFailure: $args['break'] ?? null,
					priority: $args['priority'] ?? null,
				);
			}
			if ($validatorChain->isValid($this->_getField($fieldName)->getValue())) {
				//echo " - field '$fieldName', value '{$this->_getField($fieldName)->getValue()}' is valid\n";
				unset($this->_validationErrors[$fieldName]);
			} else {
				echo " - field '$fieldName', value '{$this->_getField($fieldName)->getValue()}' is NOT valid\n";
				$this->_isValid = false;
				$this->_validationErrors[$fieldName] = array_values($validatorChain->getMessages());
			}
		}
	}

	public function getValidationErrors(): array
	{
		if (!isset($this->_validationErrors)) {
			throw new Exception('Item not validated');
		}
		return $this->_validationErrors;
	}

	public function serialize(): string
	{
		return serialize($this->getValues());
	}

	public function unserialize(string $data): void
	{
		$this->_data = unserialize($data);
	}

	public function __serialize(): array
	{
		return $this->getValues();
	}

	public function __unserialize(array $data): void
	{
		$this->_data = $data;
	}

	public function __toString(): string
	{
		return json_encode($this->getValues());
	}

	public function __call(string $name, array $args): mixed
	{
		if (count($args) === 1 && array_key_exists(0, $args) && preg_match('/^set(.+)$/', $name, $matches)) {
			$this->_setValue(lcfirst($matches[1]), $args[0]);
			return $this;
		} elseif (count($args) === 0 && preg_match('/^get(.+)$/', $name, $matches)) {
			return $this->_getValue(lcfirst($matches[1]));
		}
		throw new BadMethodCallException("Call to undefined method '$name'");
	}

	protected function _getValue($fieldName): mixed
	{
		return $this->_getField($fieldName)->getValue();
	}

	protected function _setValue(string $fieldName, $value): Field\Base
	{
		$field = $this->_getField($fieldName);
		$oldValue = isset($this->_originalValues[$fieldName]) ? $this->_originalValues[$fieldName] : $field->getValue();
		$field->setValue($value);
		$newValue = $field->getValue();
		if ($newValue === $oldValue) {
			unset($this->_originalValues[$fieldName]);
		} else {
			$this->_originalValues[$fieldName] = $oldValue;
		}
		unset($this->_isValid);
		return $field;
	}

	protected function _getField(string $fieldName): Field\Base
	{
		if (!isset(static::FIELDS[$fieldName])
			&& !isset(static::FIELDS_READONLY[$fieldName])
			&& !isset(static::FIELDS_EXTERNAL[$fieldName])
		) {
			throw new Exception("Field '$fieldName' not defined");
		}
		if (!array_key_exists($fieldName, $this->_data) || !$this->_data[$fieldName] instanceof Field\Base) {
			$fieldClassName = static::FIELDS[$fieldName]
				?? static::FIELDS_READONLY[$fieldName]
				?? static::FIELDS_EXTERNAL[$fieldName];
			$field = new $fieldClassName($fieldName);
			if (array_key_exists($fieldName, $this->_data)) {
				$field->setValue($this->_data[$fieldName]);
			} elseif (array_key_exists($fieldName, static::DEFAULTS)) {
				if ($fieldName === static::ID_INTERNAL) {
					throw new Exception('Cannot set default id, please omit from DEFAULTS');
				}
				$field->setValue(static::DEFAULTS[$fieldName]);
			} elseif ($fieldName === 'id' && $field->getValue() === 0) {
				$field->setValue(null);
			}
			$this->_data[$fieldName] = $field;
		}
		return $this->_data[$fieldName];
	}
}
