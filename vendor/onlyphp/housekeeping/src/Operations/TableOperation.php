<?php

namespace OnlyPHP\Housekeeping\Operations;

use RuntimeException;
use OnlyPHP\Housekeeping\Constants\ArchiverConstants;

class TableOperation
{
    private $config;
    private $logger;

    public function __construct($config, $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function prepareArchiveTable()
    {
        $checkTableSql = "SHOW TABLES LIKE '{$this->config->getArchiveTable()}'";
        $result = $this->config->getConnection()->execute($checkTableSql);
        $tableExists = $result->rowCount() > 0;

        if (!$tableExists) {
            $createTableSql = $this->getCreateTableSQL();

            if ($this->config->isDebug()) {
                $this->logger->log("Running create table query: \n{$createTableSql}", ArchiverConstants::LOG_LEVEL_DEBUG);
            }

            if (!$this->config->getConnection()->execute($createTableSql)) {
                $this->logger->log("Error creating archive table", ArchiverConstants::LOG_LEVEL_ERROR);
                throw new RuntimeException("Failed to create archive table");
            }

            $this->logger->log("Created archive table: {$this->config->getArchiveTable()}");
        }
    }

    private function getCreateTableSQL()
    {
        return match ($this->config->getDriver()) {
            ArchiverConstants::DRIVER_MYSQL => "CREATE TABLE IF NOT EXISTS {$this->config->getArchiveTable()} LIKE {$this->config->getOriginalTable()}",
            ArchiverConstants::DRIVER_ORACLE => "CREATE TABLE {$this->config->getArchiveTable()} AS SELECT * FROM {$this->config->getOriginalTable()} WHERE 1=2",
            default => throw new RuntimeException("Unsupported database driver for table creation")
        };
    }
}
