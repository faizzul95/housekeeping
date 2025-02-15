<?php

namespace OnlyPHP\Housekeeping\Exceptions;

use RuntimeException;
use Throwable;

class PrimaryKeyRangeException extends RuntimeException
{
    protected $context;
    protected $query;

    public function __construct(
        $message = "",
        $context = [],
        $query = null,
        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->query = $query;
    }

    /**
     * Get additional context information about the exception
     *
     * @return array
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Get the SQL query that caused the exception, if any
     *
     * @return string|null
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Create an exception for invalid column type
     *
     * @param string $columnName
     * @param string $actualType
     * @return self
     */
    public static function invalidColumnType($columnName,  $actualType)
    {
        return new self(
            sprintf("Invalid column type '%s' for primary key column '%s'", $actualType, $columnName),
            [
                'column' => $columnName,
                'type' => $actualType
            ]
        );
    }

    /**
     * Create an exception for missing column
     *
     * @param string $columnName
     * @param string $tableName
     * @return self
     */
    public static function columnNotFound($columnName,  $tableName)
    {
        return new self(
            sprintf("Primary key column '%s' not found in table '%s'", $columnName, $tableName),
            [
                'column' => $columnName,
                'table' => $tableName
            ]
        );
    }

    /**
     * Create an exception for null range values
     *
     * @param string $columnName
     * @return self
     */
    public static function nullRangeValue($columnName)
    {
        return new self(
            sprintf("Primary key range cannot be null for column '%s'", $columnName),
            ['column' => $columnName]
        );
    }

    /**
     * Create an exception for query execution failure
     *
     * @param string $query
     * @param string $error
     * @return self
     */
    public static function queryFailed($query,  $error)
    {
        return new self(
            sprintf("Failed to execute range query: %s", $error),
            ['error' => $error],
            $query
        );
    }

    /**
     * Create an exception for unsupported database driver
     *
     * @param string $driver
     * @return self
     */
    public static function unsupportedDriver($driver)
    {
        return new self(
            sprintf("Unsupported database driver: %s", $driver),
            ['driver' => $driver]
        );
    }

    /**
     * Create an exception for invalid primary key configuration
     *
     * @param string $reason
     * @param array $config
     * @return self
     */
    public static function invalidConfiguration($reason,  $config = [])
    {
        return new self(
            sprintf("Invalid primary key configuration: %s", $reason),
            ['config' => $config]
        );
    }

    /**
     * Create an exception for fetch operation failure
     *
     * @param string $operation
     * @param string $error
     * @return self
     */
    public static function fetchFailed($operation,  $error)
    {
        return new self(
            sprintf("Failed to fetch range results: %s", $error),
            [
                'operation' => $operation,
                'error' => $error
            ]
        );
    }

    /**
     * Get a formatted error message including context if available
     *
     * @return string
     */
    public function getDetailedMessage()
    {
        $message = $this->getMessage();

        if (!empty($this->context)) {
            $message .= "\nContext: " . json_encode($this->context, JSON_PRETTY_PRINT);
        }

        if ($this->query !== null) {
            $message .= "\nQuery: " . $this->query;
        }

        if ($this->getPrevious() !== null) {
            $message .= "\nCaused by: " . $this->getPrevious()->getMessage();
        }

        return $message;
    }
}
