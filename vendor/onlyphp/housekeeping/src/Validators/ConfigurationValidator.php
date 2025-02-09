<?php

namespace OnlyPHP\Housekeeping\Validators;

use RuntimeException;

class ConfigurationValidator
{
    public static function validate($config)
    {
        $requiredFields = [
            'Driver',
            'OriginalTable',
            'PrimaryKey',
            'WhereClause',
            'Mode'
        ];

        foreach ($requiredFields as $field) {
            $getter = 'get' . $field;
            if (empty($config->$getter())) {
                throw new RuntimeException("Configuration error: {$field} must be specified");
            }
        }
    }
}
