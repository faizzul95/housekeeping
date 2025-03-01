<?php

namespace OnlyPHP\Housekeeping;

use OnlyPHP\Housekeeping\Config\ArchiverConfig;
use OnlyPHP\Housekeeping\Constants\ArchiverConstants;
use OnlyPHP\Housekeeping\Operations\BackupDBOperation;
use OnlyPHP\Housekeeping\Operations\PurgeDBOperation;
use OnlyPHP\Housekeeping\Operations\TableOperation;
use OnlyPHP\Housekeeping\Results\ArchiveResult;
use OnlyPHP\Housekeeping\Validators\ConfigurationValidator;
use OnlyPHP\Housekeeping\Utils\Logger;
use OnlyPHP\Housekeeping\Utils\PrimaryKeyRangeHandler;
use OnlyPHP\Housekeeping\Operations\Parallel\ParallelManager;
use OnlyPHP\Housekeeping\Connections\DatabaseAdapter;

use Exception;
use RuntimeException;
use OnlyPHP\Housekeeping\Exceptions\PrimaryKeyRangeException;

class DatabaseArchiver
{
    private $config;
    private $logger;
    private $backupOperation;
    private $purgeOperation;
    private $tableOperation;

    public function __construct($conObj)
    {
        $connection = new DatabaseAdapter($conObj);
        $this->config = new ArchiverConfig($connection);
        $this->driver($connection->getConnectionType());

        $this->logger = new Logger($this->config->getLogPath());

        // Defer creation of operations until needed
        $this->backupOperation = null;
        $this->purgeOperation = null;
        $this->tableOperation = null;
    }

    public function driver($driver)
    {
        $this->config->setDriver($driver);
        return $this;
    }

    public function backupFrom($table)
    {
        $this->config->setOriginalTable($table);
        return $this;
    }

    public function backupTo($table)
    {
        $this->config->setArchiveTable($table);
        return $this;
    }

    public function primaryKey($primaryKey)
    {
        $this->config->setPrimaryKey($primaryKey);
        return $this;
    }

    public function whereClause($whereClause)
    {
        $this->config->setWhereClause($whereClause);
        return $this;
    }

    public function mode($mode)
    {
        $this->config->setMode($mode);
        return $this;
    }

    public function chunk($size)
    {
        $this->config->setChunkSize($size);
        return $this;
    }

    public function parallel($threads)
    {
        $this->config->setParallelThreads($threads);
        return $this;
    }

    public function uniqueColumns($columns)
    {
        $this->config->setUniqueColumns($columns);
        return $this;
    }

    public function sqlHint($hint)
    {
        $this->config->setSqlHint($hint);
        return $this;
    }

    public function onDebug()
    {
        $this->config->setDebug(true);
        return $this;
    }

    public function allowDuplicate()
    {
        $this->config->setPreventDuplicate(false);
        return $this;
    }

    public function logMessage($message, $level = ArchiverConstants::LOG_LEVEL_INFO)
    {
        $this->logger->log($message, $level);
    }

    public function run()
    {
        set_time_limit(0);
        $startTime = microtime(true);
        $initialMemory = memory_get_usage(true);

        try {
            ConfigurationValidator::validate($this->config);

            // Initialize TableOperation only when needed
            if ($this->tableOperation === null) {
                $this->tableOperation = new TableOperation($this->config, $this->logger);
            }

            $this->tableOperation->prepareArchiveTable();

            $keyRange = $this->determinePrimaryKeyRange();

            if ($this->config->getPrimaryKeyRange()['count'] === 0) {
                return ArchiveResult::createEmpty(
                    $this->config->getOriginalTable(),
                    $this->config->getArchiveTable(),
                    $startTime,
                    $this->config->getMode()
                );
            }

            // Determine the optimal chunk size based on dataset size
            $this->optimizeChunkSize($keyRange['count']);

            $processedCount = $this->isParallelSupported() && $this->config->getParallelThreads() > 1
                ? $this->runParallelArchiving()
                : $this->runSequentialArchiving();

            return ArchiveResult::createComplete(
                $this->config->getOriginalTable(),
                $this->config->getArchiveTable(),
                $startTime,
                $processedCount,
                $this->config->getPrimaryKeyRange()['count'],
                $initialMemory,
                $this->config->getParallelThreads(),
                $this->config->getMode()
            );
        } catch (Exception $e) {
            $this->logMessage("Archiving failed: " . $e->getMessage(), ArchiverConstants::LOG_LEVEL_ERROR);
            throw new RuntimeException("Archiving failed: " . $e->getMessage(), 0, $e);
        } finally {
            $this->cleanup();
        }
    }

    private function optimizeChunkSize($totalRecords)
    {
        // Adjust chunk size based on total record count
        $availableMemory = $this->config->getMemoryLimit() * 0.7; // Use 70% of available memory
        $estimatedRowSize = 4096; // Estimate average row size in bytes

        $memoryBasedChunk = floor($availableMemory / $estimatedRowSize);

        // Balance between memory and optimal DB performance
        $suggestedChunk = min(
            max(
                ArchiverConstants::MIN_CHUNK_SIZE,
                min($memoryBasedChunk, floor($totalRecords / 20)) // Try to process in ~20 chunks minimum
            ),
            ArchiverConstants::MAX_CHUNK_SIZE,
            $totalRecords // Don't exceed total records
        );

        if ($this->config->isDebug()) {
            $this->logMessage(
                "Chunk size optimization: Suggested=$suggestedChunk, Current=" . $this->config->getChunkSize(),
                ArchiverConstants::LOG_LEVEL_DEBUG
            );
        }

        // Only update if suggested chunk is significantly different
        if (abs($suggestedChunk - $this->config->getChunkSize()) > $this->config->getChunkSize() * 0.2) {
            $this->config->setChunkSize($suggestedChunk);

            if ($this->config->isDebug()) {
                $this->logMessage(
                    "Chunk size optimized to: " . $this->config->getChunkSize(),
                    ArchiverConstants::LOG_LEVEL_DEBUG
                );
            }
        }
    }

    private function determinePrimaryKeyRange()
    {
        $startTime = microtime(true);
        $tableName = $this->config->getOriginalTable();
        $primaryKey = $this->config->getPrimaryKey();

        try {
            // Validate basic configuration
            if (empty($tableName)) {
                throw PrimaryKeyRangeException::invalidConfiguration("Table name is required");
            }

            if (empty($primaryKey)) {
                throw PrimaryKeyRangeException::invalidConfiguration("Primary key column is required");
            }

            // Initialize range handler
            $rangeHandler = new PrimaryKeyRangeHandler($this->config, $this->logger);

            // Get the range
            $range = $rangeHandler->determineRange();

            // Handle empty result set
            if ($range['count'] === 0) {
                $this->logMessage(
                    sprintf(
                        "No records found matching criteria in table '%s' where %s",
                        $tableName,
                        $this->config->getWhereClause()
                    ),
                    ArchiverConstants::LOG_LEVEL_WARNING
                );
            } else if ($this->config->isDebug()) {
                $this->logMessage(
                    sprintf(
                        "Found %d records in range [%s - %s] in table '%s'",
                        $range['count'],
                        var_export($range['min'], true),
                        var_export($range['max'], true),
                        $tableName
                    ),
                    ArchiverConstants::LOG_LEVEL_DEBUG
                );
            }

            // Set the range in configuration
            $this->config->setPrimaryKeyRange($range);

            // Log debug information if enabled
            if ($this->config->isDebug()) {
                $this->logMessage(
                    sprintf(
                        "Primary key range determined for table '%s':\n" .
                            "- Column: %s\n" .
                            "- Min: %s\n" .
                            "- Max: %s\n" .
                            "- Count: %d\n" .
                            "- Execution Time: %s",
                        $tableName,
                        $primaryKey,
                        var_export($range['min'], true),
                        var_export($range['max'], true),
                        $range['count'],
                        ArchiveResult::calculateRuntime($startTime, microtime(true))
                    ),
                    ArchiverConstants::LOG_LEVEL_DEBUG
                );
            }

            // Memory cleanup for large datasets
            unset($rangeHandler);
            $this->performGarbageCollection("after range determination");

            return $range;
        } catch (PrimaryKeyRangeException $e) {
            $this->logMessage(
                sprintf(
                    "Failed to determine primary key range for table '%s': %s",
                    $tableName,
                    $e->getDetailedMessage()
                ),
                ArchiverConstants::LOG_LEVEL_ERROR
            );

            // Add context to the original exception
            throw new RuntimeException(
                sprintf(
                    "Failed to determine primary key range for table '%s'. %s",
                    $tableName,
                    $e->getMessage()
                ),
                0,
                $e
            );
        } catch (Exception $e) {
            $this->logMessage(
                sprintf(
                    "Unexpected error while determining primary key range for table '%s': %s",
                    $tableName,
                    $e->getMessage()
                ),
                ArchiverConstants::LOG_LEVEL_ERROR
            );

            throw new RuntimeException(
                sprintf(
                    "Unexpected error while determining primary key range for table '%s'. %s",
                    $tableName,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    private function runSequentialArchiving()
    {
        $backupCount = 0;
        $purgeCount = 0;
        $range = $this->config->getPrimaryKeyRange();
        $currentId = $range['min'];
        $maxId = $range['max'];
        $chunkSize = $this->config->getChunkSize();
        $totalIdCount = $maxId - $currentId + 1;
        $processedCount = 0;
        $lastProgressReport = 0;

        // Initialize operations only when needed
        $this->initializeOperations();

        while ($currentId <= $maxId) {
            $remainingIds = $maxId - $currentId + 1;
            $currentChunkSize = min($chunkSize, $remainingIds);

            $endId = $currentId + $currentChunkSize - 1;
            $processedInChunk = $this->processIdRange($currentId, $endId);

            $backupCount += $processedInChunk['backup'];
            $purgeCount += $processedInChunk['purge'];

            $processedCount += $currentChunkSize;
            $progressPercent = floor(($processedCount / $totalIdCount) * 100);

            // Report progress at 5% increments
            if ($progressPercent - $lastProgressReport >= 5) {
                $this->logMessage(
                    sprintf(
                        "Processing progress: %d%% complete (%d/%d IDs)",
                        $progressPercent,
                        $processedCount,
                        $totalIdCount
                    ),
                    ArchiverConstants::LOG_LEVEL_INFO
                );
                $lastProgressReport = $progressPercent;

                // Periodically collect garbage
                $this->performGarbageCollection();
            }

            $currentId += $currentChunkSize;
        }

        return ['backup' => $backupCount, 'purge' => $purgeCount];
    }

    private function runParallelArchiving()
    {
        if (!$this->isParallelSupported()) {
            $this->logMessage("Parallel processing not supported. Falling back to sequential.", ArchiverConstants::LOG_LEVEL_WARNING);
            return $this->runSequentialArchiving();
        }

        // Initialize operations before parallelization
        $this->initializeOperations();

        $parallelManager = new ParallelManager($this->logger, $this->config);
        return $parallelManager->execute(
            $this->config->getPrimaryKeyRange(),
            [$this, 'processIdRange']
        );
    }

    public function processIdRange($startId, $endId)
    {
        // Make sure operations are initialized
        $this->initializeOperations();

        $backupCount = 0;
        $purgeCount = 0;
        $idRangeCondition = "{$this->config->getPrimaryKey()} BETWEEN :0 AND :1";
        $params = [$startId, $endId];

        try {
            if (in_array($this->config->getMode(), [ArchiverConstants::MODE_BACKUP_ONLY, ArchiverConstants::MODE_BACKUP_PURGE])) {
                $backupCount = $this->backupOperation->execute($idRangeCondition, $params);

                if ($this->config->isDebug() && $backupCount > 0) {
                    $this->logMessage(
                        "Backed up {$backupCount} records from range {$startId}-{$endId}",
                        ArchiverConstants::LOG_LEVEL_DEBUG
                    );
                }
            }

            if (in_array($this->config->getMode(), [ArchiverConstants::MODE_PURGE_ONLY, ArchiverConstants::MODE_BACKUP_PURGE])) {
                $purgeCount = $this->purgeOperation->execute($idRangeCondition, $params);

                if ($this->config->isDebug() && $purgeCount > 0) {
                    $this->logMessage(
                        "Purged {$purgeCount} records from range {$startId}-{$endId}",
                        ArchiverConstants::LOG_LEVEL_DEBUG
                    );
                }
            }

            $this->performGarbageCollection();
        } catch (Exception $e) {
            $this->logMessage(
                "Processing error at ID range {$startId}-{$endId}: " . $e->getMessage(),
                ArchiverConstants::LOG_LEVEL_ERROR
            );
        }

        return ['backup' => $backupCount, 'purge' => $purgeCount];
    }

    private function initializeOperations()
    {
        // Lazy initialization of operation objects
        if ($this->backupOperation === null && in_array($this->config->getMode(), [ArchiverConstants::MODE_BACKUP_ONLY, ArchiverConstants::MODE_BACKUP_PURGE])) {
            $this->backupOperation = new BackupDBOperation($this->config, $this->logger);
        }

        if ($this->purgeOperation === null && in_array($this->config->getMode(), [ArchiverConstants::MODE_PURGE_ONLY, ArchiverConstants::MODE_BACKUP_PURGE])) {
            $this->purgeOperation = new PurgeDBOperation($this->config, $this->logger);
        }

        if ($this->tableOperation === null) {
            $this->tableOperation = new TableOperation($this->config, $this->logger);
        }
    }

    private function isParallelSupported()
    {
        if (!$this->config->getParallelEnableStatus()) {
            return false;
        }

        // Check if Windows can execute background processes using `start /B`
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            return function_exists('popen') && function_exists('pclose');
        }

        // Linux/Unix: Check for required PCNTL and POSIX functions
        return function_exists('pcntl_fork')
            && function_exists('pcntl_signal')
            && function_exists('posix_kill')
            && function_exists('posix_getpid');
    }


    private function performGarbageCollection($context = '', $forceCleanup = false)
    {
        // Check current memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->config->getMemoryLimit();
        $memoryPercent = ($memoryUsage / $memoryLimit) * 100;

        // Force garbage collection if memory usage is above 70%
        if (($memoryPercent > 70 || $forceCleanup) && function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();

            if ($this->config->isDebug()) {
                $this->logMessage(
                    sprintf(
                        "Memory optimization: Used %.2f%% of limit. Garbage collection freed %d references%s.",
                        $memoryPercent,
                        $collected,
                        !empty($context) ? " $context" : ""
                    ),
                    ArchiverConstants::LOG_LEVEL_DEBUG
                );
            }

            unset($collected);

            return true;
        }

        return false;
    }

    private function cleanup()
    {
        $this->config->setPrimaryKeyRange(null);

        // Clear operation references to free memory
        $this->backupOperation = null;
        $this->purgeOperation = null;
        $this->tableOperation = null;

        $this->performGarbageCollection("in cleanup", true);
        $this->logger->rotateLogIfNeeded();
    }
}
