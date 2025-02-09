<?php

namespace OnlyPHP\Housekeeping;

use OnlyPHP\Housekeeping\Config\ArchiverConfig;
use OnlyPHP\Housekeeping\Constants\ArchiverConstants;
use OnlyPHP\Housekeeping\Operations\BackupOperation;
use OnlyPHP\Housekeeping\Operations\PurgeOperation;
use OnlyPHP\Housekeeping\Operations\TableOperation;
use OnlyPHP\Housekeeping\Results\ArchiveResult;
use OnlyPHP\Housekeeping\Validators\ConfigurationValidator;
use OnlyPHP\Housekeeping\Utils\Logger;
use OnlyPHP\Housekeeping\Operations\Parallel\ParallelManager;
use OnlyPHP\Housekeeping\Connections\DatabaseAdapter;

use Exception;
use RuntimeException;

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
        $this->logger = new Logger($this->config->getLogPath());
        $this->backupOperation = new BackupOperation($this->config, $this->logger);
        $this->purgeOperation = new PurgeOperation($this->config, $this->logger);
        $this->tableOperation = new TableOperation($this->config, $this->logger);
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
            $this->tableOperation->prepareArchiveTable();

            $this->determinePrimaryKeyRange();

            if ($this->config->getPrimaryKeyRange()['count'] === 0) {
                return ArchiveResult::createEmpty(
                    $this->config->getOriginalTable(),
                    $this->config->getArchiveTable(),
                    $startTime,
                    $this->config->getMode()
                );
            }

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

    private function determinePrimaryKeyRange()
    {
        $sql = "SELECT MIN({$this->config->getPrimaryKey()}) as min_id, 
                       MAX({$this->config->getPrimaryKey()}) as max_id, 
                       COUNT(*) as total_count
                FROM {$this->config->getOriginalTable()}
                WHERE {$this->config->getWhereClause()}";

        if ($this->config->isDebug()) {
            $this->logMessage("Running range query: \n{$sql}", ArchiverConstants::LOG_LEVEL_DEBUG);
        }

        $stmt = $this->config->getConnection()->execute($sql);
        $result = $stmt ? $stmt->fetch() : null;

        $this->config->setPrimaryKeyRange([
            'min' => !empty($result) && isset($result['min_id']) ? (int)$result['min_id'] : 0,
            'max' => !empty($result) && isset($result['max_id']) ? (int)$result['max_id'] : 0,
            'count' => !empty($result) && isset($result['total_count']) ? (int)$result['total_count'] : 0
        ]);
    }

    private function runSequentialArchiving()
    {
        $backupCount = 0;
        $purgeCount = 0;
        $range = $this->config->getPrimaryKeyRange();
        $currentId = $range['min'];
        $maxId = $range['max'];

        while ($currentId <= $maxId) {
            $remainingIds = $maxId - $currentId + 1;
            $currentChunkSize = min($this->config->getChunkSize(), $remainingIds);

            $processedInChunk = $this->processChunk($currentId);
            $backupCount += $processedInChunk['backup'];
            $purgeCount += $processedInChunk['purge'];

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

        $parallelManager = new ParallelManager($this->logger, $this->config);
        return $parallelManager->execute(
            $this->config->getPrimaryKeyRange(),
            [$this, 'processIdRange']
        );
    }

    private function processIdRange($startId, $endId)
    {
        $backupCount = 0;
        $purgeCount = 0;
        $currentId = $startId;

        while ($currentId <= $endId) {
            $remainingIds = $endId - $currentId + 1;
            $currentChunkSize = min($this->config->getChunkSize(), $remainingIds);

            $processedInChunk = $this->processChunk($currentId);
            $backupCount += $processedInChunk['backup'];
            $purgeCount += $processedInChunk['purge'];
            $currentId += $currentChunkSize;
        }

        return ['backup' => $backupCount, 'purge' => $purgeCount];
    }

    private function processChunk($startId)
    {
        $remainingIds = $this->config->getPrimaryKeyRange()['max'] - $startId + 1;
        $currentChunkSize = min($this->config->getChunkSize(), $remainingIds);
        $endId = $startId + $currentChunkSize - 1;

        $idRangeCondition = "{$this->config->getPrimaryKey()} BETWEEN {$startId} AND {$endId}";

        $backupCount = 0;
        $purgeCount = 0;

        $this->logMessage("Starting processing for ID range: {$startId} to {$endId}");

        try {
            if (in_array($this->config->getMode(), [ArchiverConstants::MODE_BACKUP_ONLY, ArchiverConstants::MODE_BACKUP_PURGE])) {
                $backupCount = $this->backupOperation->execute($idRangeCondition);
            }

            if (in_array($this->config->getMode(), [ArchiverConstants::MODE_PURGE_ONLY, ArchiverConstants::MODE_BACKUP_PURGE])) {
                $purgeCount = $this->purgeOperation->execute($idRangeCondition);
            }
        } catch (Exception $e) {
            $this->logMessage("Processing error at ID range" . $idRangeCondition . ": " . $e->getMessage(), ArchiverConstants::LOG_LEVEL_ERROR);
        }

        return ['backup' => $backupCount, 'purge' => $purgeCount];
    }

    private function isParallelSupported()
    {
        return $this->config->getParallelEnableStatus()
            && function_exists('pcntl_fork')
            && function_exists('pcntl_signal')
            && function_exists('posix_kill')
            && function_exists('posix_getpid');
    }

    private function cleanup()
    {
        $this->config->setPrimaryKeyRange(null);

        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        $this->logger->rotateLogIfNeeded();
    }
}
