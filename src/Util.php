<?php

/**
 * Data Model Util class.
 */

declare(strict_types=1);

namespace FasterPhp\DataModel;

/**
 * Data Model Util class.
 */
class Util
{
    public static function getItemClassName(string $callingClassName): string
    {
        return self::getClassName($callingClassName, 'Item');
    }

    public static function getSetClassName(string $callingClassName): string
    {
        return self::getClassName($callingClassName, 'Set');
    }

    public static function getRepositoryClassName(string $callingClassName): string
    {
        return self::getClassName($callingClassName, 'Repository');
    }

    protected static function getClassName(string $callingClassName, string $classNameSuffix): string
    {
        return preg_replace('/(Item|Set|Repository)$/', $classNameSuffix, $callingClassName);
    }
}
