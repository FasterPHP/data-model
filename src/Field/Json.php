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
    public function setValue($value): self
    {
        if (is_null($value)) {
            $this->value = $value;

        // Decode value
        } elseif (is_string($value)) {
            try {
                $this->value = json_decode($value, true, JSON_THROW_ON_ERROR);
            } catch (JsonException $ex) {
                throw new InvalidArgumentException($ex->getMessage());
            }

        // Encode value
        } else {
            try {
                $this->value = json_encode($value, JSON_THROW_ON_ERROR);
            } catch (JsonException $ex) {
                throw new InvalidArgumentException($ex->getMessage());
            }
        }
        return $this;
    }
}
