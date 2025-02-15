<?php

namespace OnlyPHP\Housekeeping\Utils;

use OnlyPHP\Housekeeping\Config\ArchiverConfig;
use OnlyPHP\Housekeeping\Constants\ArchiverConstants;
use OnlyPHP\Housekeeping\Exceptions\PrimaryKeyRangeException;
use Exception;

class PrimaryKeyRangeHandler
{
    private $config;
    private $logger;
    private $columnInfo;
    private $connectionType;

    public function __construct(ArchiverConfig $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->connectionType = $this->config->getConnection()->getConnectionType();
        $this->columnInfo = $this->getPrimaryKeyInfo();
    }

    public function determineRange()
    {
        $driver = $this->config->getDriver();
        $rangeQuery = $this->buildRangeQuery($driver);
        $params = [];

        if ($this->config->isDebug()) {
            $this->logger->log("Running range query: \n{$rangeQuery}", ArchiverConstants::LOG_LEVEL_DEBUG);
        }

        try {
            $stmt = $this->config->getConnection()->execute($rangeQuery, $params);
            $result = $this->fetchResult($stmt);

            if (empty($result) || !isset($result['min_id']) || !isset($result['max_id'])) {
                throw new PrimaryKeyRangeException("Failed to determine primary key range");
            }

            return [
                'min' => $this->castValue($result['min_id']),
                'max' => $this->castValue($result['max_id']),
                'count' => (int)$result['total_count']
            ];
        } catch (Exception $e) {
            throw new PrimaryKeyRangeException("Failed to execute range query: " . $e->getMessage());
        }
    }

    private function fetchResult($stmt)
    {
        if (!$stmt) {
            throw new PrimaryKeyRangeException("Invalid statement object");
        }

        // Use row_array() method for all connection types if available
        if (method_exists($stmt, 'row_array')) {
            $result = $stmt->row_array();
            if ($result) {
                return $result;
            }
        }

        // Fallback to specific connection types if row_array() didn't work
        switch ($this->connectionType) {
            case 'pdo':
            case 'pdo_oci':
                return $stmt->fetch() ?: [];

            case 'mysqli':
            case 'mariadb':
                return $stmt->fetch() ?: [];

            case 'codeigniter3':
            case 'codeigniter3_oci':
                return method_exists($stmt, 'row_array') ? $stmt->row_array() : [];

            case 'oci':
            case 'oracle':
            case 'oci8':
                if (is_resource($stmt->statement) && function_exists('oci_fetch_assoc')) {
                    $result = oci_fetch_assoc($stmt->statement);
                    if ($result) {
                        // Convert all keys to lowercase for consistency
                        return array_change_key_case($result, CASE_LOWER);
                    }
                }
                return [];

            default:
                throw new PrimaryKeyRangeException("Unsupported connection type for fetch operation: " . $this->connectionType);
        }
    }

    private function getPrimaryKeyInfo()
    {
        $table = $this->config->getOriginalTable();
        $column = $this->config->getPrimaryKey();
        $driver = $this->config->getDriver();

        $query = $this->buildColumnInfoQuery($driver, $table, $column);

        try {
            $stmt = $this->config->getConnection()->execute($query);
            $result = $this->fetchResult($stmt);

            if (empty($result)) {
                throw new PrimaryKeyRangeException("Could not determine column type for {$column}");
            }

            // Normalize field names based on connection type and driver
            // Make all keys lowercase for case-insensitive access
            $result = array_change_key_case($result, CASE_LOWER);

            $dataType = $result['data_type'] ??
                $result['type'] ??
                $result['column_type'] ?? '';

            $length = $result['character_maximum_length'] ??
                $result['data_length'] ??
                $result['length'] ??
                null;

            return [
                'type' => $this->normalizeDataType(strtolower($dataType)),
                'length' => $length
            ];
        } catch (Exception $e) {
            throw new PrimaryKeyRangeException("Failed to get column info: " . $e->getMessage());
        }
    }

    private function buildColumnInfoQuery($driver, $table, $column)
    {
        return match ($driver) {
            'pdo', 'mysqli', 'mariadb', 'codeigniter3' => "
                SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, 
                       NUMERIC_PRECISION, NUMERIC_SCALE
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_NAME = '{$table}' 
                AND COLUMN_NAME = '{$column}'
            ",
            'pdo_oci', 'oracle', 'oci', 'oci8', 'codeigniter3_oci' => "
                SELECT COLUMN_NAME, DATA_TYPE, DATA_LENGTH, DATA_PRECISION, DATA_SCALE
                FROM ALL_TAB_COLUMNS
                WHERE TABLE_NAME = UPPER('{$table}')
                AND COLUMN_NAME = UPPER('{$column}')
            ",
            default => throw new PrimaryKeyRangeException("Unsupported database driver: {$driver}")
        };
    }

    private function buildRangeQuery($driver)
    {
        $pk = $this->config->getPrimaryKey();
        $table = $this->config->getOriginalTable();
        $where = $this->config->getWhereClause();

        $minMaxExpr = $this->getMinMaxExpression($driver, $pk);

        return match ($driver) {
            'pdo', 'mysqli', 'mariadb', 'codeigniter3' => "
                SELECT {$minMaxExpr}, COUNT(*) as total_count
                FROM {$table}" .
                ($driver === 'mysql' ? " FORCE INDEX (PRIMARY)" : "") . "
                WHERE {$where}
            ",
            'pdo_oci', 'oracle', 'oci', 'oci8', 'codeigniter3_oci' => "
                SELECT {$minMaxExpr}, COUNT(*) as total_count
                FROM {$table}
                WHERE {$where}
                AND ROWNUM > 0
            ",
            default => throw new PrimaryKeyRangeException("Unsupported database driver: {$driver}")
        };
    }

    private function getMinMaxExpression($driver, $column)
    {
        return match ($this->columnInfo['type']) {
            'varchar', 'char', 'text', 'clob' => $this->getStringRangeExpression($driver, $column),
            'uuid' => $this->getUUIDRangeExpression($driver, $column),
            'binary', 'varbinary' => $this->getBinaryRangeExpression($driver, $column),
            default => "MIN({$column}) as min_id, MAX({$column}) as max_id"
        };
    }

    private function getStringRangeExpression($driver, $column)
    {
        return match ($driver) {
            'pdo', 'mysqli', 'mariadb', 'codeigniter3' => "
                MIN({$column}) as min_id,
                MAX({$column}) as max_id
            ",
            'pdo_oci', 'oracle', 'oci', 'oci8', 'codeigniter3_oci' => "
                MIN(TO_CHAR({$column})) as min_id,
                MAX(TO_CHAR({$column})) as max_id
            "
        };
    }

    private function getUUIDRangeExpression($driver, $column)
    {
        return match ($driver) {
            'pdo', 'mysqli', 'mariadb', 'codeigniter3' => "
                MIN(UNHEX(REPLACE({$column}, '-', ''))) as min_id,
                MAX(UNHEX(REPLACE({$column}, '-', ''))) as max_id
            ",
            'pdo_oci', 'oracle', 'oci', 'oci8', 'codeigniter3_oci' => "
                MIN(HEXTORAW(REPLACE({$column}, '-', ''))) as min_id,
                MAX(HEXTORAW(REPLACE({$column}, '-', ''))) as max_id
            "
        };
    }

    private function getBinaryRangeExpression($driver, $column)
    {
        return match ($driver) {
            'pdo', 'mysqli', 'mariadb', 'codeigniter3' => "
                MIN(HEX({$column})) as min_id,
                MAX(HEX({$column})) as max_id
            ",
            'pdo_oci', 'oracle', 'oci', 'oci8', 'codeigniter3_oci' => "
                MIN(RAWTOHEX({$column})) as min_id,
                MAX(RAWTOHEX({$column})) as max_id
            "
        };
    }

    private function normalizeDataType($type)
    {
        // Extract base type for types with length specification (e.g., "varchar(255)" -> "varchar")
        if (strpos($type, '(') !== false) {
            $type = substr($type, 0, strpos($type, '('));
        }

        $typeMap = [
            // Numeric types
            'tinyint' => 'int',
            'smallint' => 'int',
            'mediumint' => 'int',
            'bigint' => 'int',
            'decimal' => 'decimal',
            'numeric' => 'decimal',
            'float' => 'float',
            'double' => 'double',
            'number' => 'decimal', // Oracle NUMBER type

            // String types
            'char' => 'char',
            'varchar' => 'varchar',
            'varchar2' => 'varchar', // Oracle VARCHAR2
            'nvarchar' => 'varchar', // Oracle NVARCHAR
            'nvarchar2' => 'varchar', // Oracle NVARCHAR2
            'tinytext' => 'text',
            'text' => 'text',
            'mediumtext' => 'text',
            'longtext' => 'text',
            'clob' => 'clob',
            'nclob' => 'clob',
            'long' => 'clob', // Oracle LONG type

            // Binary types
            'binary' => 'binary',
            'varbinary' => 'binary',
            'tinyblob' => 'binary',
            'blob' => 'binary',
            'mediumblob' => 'binary',
            'longblob' => 'binary',
            'raw' => 'binary', // Oracle RAW type
            'long raw' => 'binary', // Oracle LONG RAW type

            // Special types
            'guid' => 'uuid',
            'uniqueidentifier' => 'uuid'
        ];

        return $typeMap[strtolower($type)] ?? $type;
    }

    private function castValue($value)
    {
        if ($value === null) {
            throw new PrimaryKeyRangeException("Primary key range cannot be null");
        }

        return match ($this->columnInfo['type']) {
            'int' => (int)$value,
            'decimal', 'float', 'double' => (float)$value,
            'binary' => is_resource($value) ? stream_get_contents($value) : $value,
            'uuid' => (string)$value,
            'clob' => is_resource($value) ? stream_get_contents($value) : (string)$value,
            default => (string)$value
        };
    }
}
