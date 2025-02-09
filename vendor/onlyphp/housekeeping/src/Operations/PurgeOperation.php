<?php

namespace OnlyPHP\Housekeeping\Operations;

use OnlyPHP\Housekeeping\Constants\ArchiverConstants;
use OnlyPHP\Housekeeping\Utils\MemoryManager;

use Exception;
use RuntimeException;

class PurgeOperation
{
    private $config;
    private $logger;

    public function __construct($config, $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function execute($idRangeCondition)
    {
        MemoryManager::checkMemoryUsage($this->config, $this->logger);

        $uniqueCondition = $this->buildUniqueCondition();
        $purgeSql = $this->buildPurgeQuery($idRangeCondition, $uniqueCondition);

        if ($this->config->isDebug()) {
            $this->logger->log("Running purge query: \n{$purgeSql}", ArchiverConstants::LOG_LEVEL_DEBUG);
        }

        $connection = $this->config->getConnection();
        $connection->beginTransaction(); // Start transaction
        try {
            $result = $connection->execute($purgeSql);

            if ($result === false) {
                throw new RuntimeException("Query execution returned no result for this query : {$purgeSql}.");
            }

            $connection->commit(); // Commit transaction
        } catch (Exception $e) {
            $this->logger->log("Error executing query: " . $e->getMessage(), ArchiverConstants::LOG_LEVEL_ERROR);
            $connection->rollback(); // Rollback on error
            return 0; // Return 0 rows affected
        }

        return $result->rowCount();
    }

    private function buildUniqueCondition()
    {
        // Only apply the unique condition check for BP mode
        if ($this->config->getMode() !== ArchiverConstants::MODE_BACKUP_PURGE) {
            return '';
        }

        $conditions = [];
        foreach ($this->config->getUniqueColumns() as $column) {
            $conditions[] = "arc.{$column} = o.{$column}";
        }

        return ' AND EXISTS (
            SELECT 1 FROM ' . $this->config->getArchiveTable() . ' arc WHERE ' .
            implode(' AND ', $conditions) . ')';
    }

    private function buildPurgeQuery($idRangeCondition, $uniqueCondition)
    {
        return "DELETE FROM {$this->config->getOriginalTable()} o
                WHERE {$idRangeCondition}
                AND ({$this->config->getWhereClause()})
                {$uniqueCondition}";
    }
}
