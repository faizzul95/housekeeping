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

        $purgeSql = $this->buildPurgeQuery($idRangeCondition);

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

    private function buildPurgeQuery($idRangeCondition)
    {
        return "DELETE FROM {$this->config->getOriginalTable()}
                WHERE {$idRangeCondition}
                AND ({$this->config->getWhereClause()})";
    }
}
