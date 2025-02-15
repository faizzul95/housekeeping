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
            // MySQL-based drivers 
            ArchiverConstants::DRIVER_MYSQL,
            ArchiverConstants::DRIVER_MYSQLI,
            ArchiverConstants::DRIVER_MARIADB,
            ArchiverConstants::DRIVER_PDO_MYSQL,
            ArchiverConstants::DRIVER_CODEIGNITER3_MYSQL
            => "CREATE TABLE IF NOT EXISTS {$this->config->getArchiveTable()} AS SELECT * FROM {$this->config->getOriginalTable()} WHERE 1=2",

            // Oracle-based drivers 
            ArchiverConstants::DRIVER_ORACLE,
            ArchiverConstants::DRIVER_PDO_OCI,
            ArchiverConstants::DRIVER_CODEIGNITER3_OCI
            => "CREATE TABLE {$this->config->getArchiveTable()} AS SELECT * FROM {$this->config->getOriginalTable()} WHERE 1=2",

            default => throw new RuntimeException("Unsupported database driver '{$this->config->getDriver()}' for table creation")
        };
    }
}
