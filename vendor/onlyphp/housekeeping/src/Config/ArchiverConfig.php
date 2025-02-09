<?php

namespace OnlyPHP\Housekeeping\Config;

use OnlyPHP\Housekeeping\Constants\ArchiverConstants;
use OnlyPHP\Housekeeping\Utils\MemoryManager;

use RuntimeException;
use InvalidArgumentException;

class ArchiverConfig
{
    private $connection;
    private $driver;
    private $originalTable;
    private $archiveTable;
    private $primaryKey;
    private $whereClause;
    private $mode;
    private $chunkSize = ArchiverConstants::DEFAULT_CHUNK_SIZE;
    private $parallelEnableStatus = ArchiverConstants::DEFAULT_PARALLEL_ENABLED;
    private $parallelThreads = ArchiverConstants::DEFAULT_PARALLEL_THREADS;
    private $logPath;
    private $sqlHint = '';
    private $uniqueColumns = [];
    private $debug = false;
    private $preventDuplicate = true;
    private $memoryLimit;
    private $startMemory;
    private $primaryKeyRange = null;

    public function __construct($connection)
    {
        $this->connection = $connection;
        $this->logPath = $this->getProjectRoot() . '/logs/archive_' . date('Y-m-d') . '.log';
        $this->memoryLimit = MemoryManager::getMemoryLimitInBytes();
        $this->startMemory = memory_get_usage(true);
    }

    // Getters
    public function getConnection()
    {
        return $this->connection;
    }

    public function getDriver()
    {
        return $this->driver;
    }

    public function getOriginalTable()
    {
        return $this->originalTable;
    }

    public function getArchiveTable()
    {
        return $this->archiveTable;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function getWhereClause()
    {
        return $this->whereClause;
    }

    public function getMode()
    {
        return $this->mode;
    }

    public function getChunkSize()
    {
        return $this->chunkSize;
    }

    public function getParallelEnableStatus()
    {
        return $this->parallelEnableStatus;
    }

    public function getParallelThreads()
    {
        return $this->parallelThreads;
    }

    public function getLogPath()
    {
        return $this->logPath;
    }

    public function getSqlHint()
    {
        return $this->sqlHint;
    }

    public function getUniqueColumns()
    {
        return $this->uniqueColumns;
    }

    public function isDebug()
    {
        return $this->debug;
    }

    public function isPreventDuplicate()
    {
        return $this->preventDuplicate;
    }

    public function getMemoryLimit()
    {
        return $this->memoryLimit;
    }

    public function getStartMemory()
    {
        return $this->startMemory;
    }

    public function getPrimaryKeyRange()
    {
        return $this->primaryKeyRange;
    }

    private function getProjectRoot()
    {
        return dirname(__DIR__, 5);
    }

    // Setters with validation
    public function setDriver($driver)
    {
        $supportedDrivers = [ArchiverConstants::DRIVER_MYSQL, ArchiverConstants::DRIVER_ORACLE];
        $driver = strtolower($driver);

        if (!in_array($driver, $supportedDrivers, true)) {
            throw new InvalidArgumentException("Unsupported database driver: {$driver}");
        }

        $this->driver = $driver;
    }

    public function setOriginalTable($table)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException("Invalid source table name format");
        }

        $this->originalTable = $table;
        $this->archiveTable = $table . '_ARC';
    }

    public function setArchiveTable($table)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new InvalidArgumentException("Invalid target table name format");
        }

        $this->archiveTable = $table;
    }

    public function setPrimaryKey($primaryKey)
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $primaryKey)) {
            throw new InvalidArgumentException("Invalid primary key name");
        }

        $this->primaryKey = $primaryKey;
        $this->setUniqueColumns($primaryKey);
    }

    public function setWhereClause($whereClause)
    {
        if (empty($whereClause)) {
            throw new InvalidArgumentException("WHERE clause cannot be empty");
        }

        $this->whereClause = trim($whereClause);
    }

    public function setMode($mode)
    {
        $validModes = [
            ArchiverConstants::MODE_BACKUP_ONLY,
            ArchiverConstants::MODE_PURGE_ONLY,
            ArchiverConstants::MODE_BACKUP_PURGE
        ];

        if (!in_array($mode, $validModes, true)) {
            throw new InvalidArgumentException("Invalid mode. Use 'BO', 'PO', or 'BP'");
        }

        $this->mode = $mode;
    }

    public function setChunkSize($size)
    {
        $this->chunkSize = max(
            ArchiverConstants::MIN_CHUNK_SIZE,
            min($size, ArchiverConstants::MAX_CHUNK_SIZE)
        );
    }

    public function setParallelEnableStatus($status)
    {
        $this->parallelEnableStatus = $status;
    }

    public function setParallelThreads($threads)
    {
        $this->parallelThreads = max(
            ArchiverConstants::PARALLEL_MIN_THREADS,
            min($threads, ArchiverConstants::PARALLEL_MAX_THREADS)
        );
    }

    public function setLogPath($path)
    {
        $directory = dirname($path);

        if (!file_exists($directory)) {
            if (!mkdir($directory, 0777, true)) {
                throw new RuntimeException("Unable to create log directory: {$directory}");
            }
        }

        if (!is_writable($directory)) {
            throw new RuntimeException("Log directory is not writable: {$directory}");
        }

        $this->logPath = $path;
    }

    public function setSqlHint($hint)
    {
        if (empty(trim($hint))) {
            throw new InvalidArgumentException("SQL hint cannot be empty");
        }

        $this->sqlHint = $hint;
    }

    public function setUniqueColumns($columns)
    {
        if (empty($columns)) {
            throw new InvalidArgumentException("uniqueColumns cannot be empty");
        }

        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }

        foreach ($columns as $key => $column) {
            $column = preg_replace('/[^a-zA-Z0-9_]/', '', trim($column));
            if ($column === '') {
                throw new InvalidArgumentException("Invalid column name: {$column}");
            }
            $columns[$key] = $column;
        }

        $this->uniqueColumns = array_unique(array_merge($this->uniqueColumns ?? [], $columns));
    }

    public function setDebug($debug)
    {
        $this->debug = (bool)$debug;
    }

    public function setPreventDuplicate($prevent)
    {
        $this->preventDuplicate = (bool)$prevent;
    }

    public function setPrimaryKeyRange($range)
    {
        $this->primaryKeyRange = $range;
    }
}
