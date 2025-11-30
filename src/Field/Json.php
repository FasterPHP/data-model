<?php

/**
 * JSON Field class.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel\Field;

use InvalidArgumentException;
use JsonException;

/**
 * JSON Field class.
 */
class Json extends Base
{
    protected function setValueInternal(mixed $value): self
    {
        if (is_null($value)) {
            $this->value = $value;

        // Decode JSON string to array
        } elseif (is_string($value)) {
            // Fast-path on PHP 8.3+
            if (function_exists('json_validate') && false === json_validate($value)) {
                throw new InvalidArgumentException('Invalid JSON string');
            }
            try {
                $this->value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $ex) {
                throw new InvalidArgumentException($ex->getMessage());
            }

        // Store array/object directly
        } elseif (is_array($value) || is_object($value)) {
            $this->value = is_object($value) ? (array) $value : $value;

        } else {
            throw new InvalidArgumentException("{$this->name} value must be a JSON string, array, or object");
        }
        return $this;
    }

    public function getSqlValue(): ?string
    {
        if (is_null($this->value)) {
            return null;
        }
        try {
            return json_encode($this->value, JSON_THROW_ON_ERROR);
        } catch (JsonException $ex) {
            throw new InvalidArgumentException($ex->getMessage());
        }
    }
}
