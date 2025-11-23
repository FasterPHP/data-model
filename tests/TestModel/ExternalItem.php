<?php

declare(strict_types=1);

namespace FasterPhp\DataModel\TestModel;

use FasterPhp\DataModel\Item;
use FasterPhp\DataModel\Field;

/**
 * Test fixture for FIELDS_EXTERNAL testing.
 */
class ExternalItem extends Item
{
    public const ID_FIELD = 'empId';
    
    public const FIELDS = [
        'id' => Field\Integer::class,
        'name' => Field\Varchar::class,
        'departmentId' => Field\Integer::class,
    ];
    
    public const FIELDS_EXTERNAL = [
        'departmentName' => Field\Varchar::class,
    ];
}
