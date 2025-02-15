<?php

namespace OnlyPHP\Housekeeping\Constants;

class ArchiverConstants
{
    // Operation modes
    public const MODE_BACKUP_ONLY = 'BO';
    public const MODE_PURGE_ONLY = 'PO';
    public const MODE_BACKUP_PURGE = 'BP';

    // Logging levels
    public const LOG_LEVEL_INFO = 'INFO';
    public const LOG_LEVEL_ERROR = 'ERROR';
    public const LOG_LEVEL_WARNING = 'WARNING';
    public const LOG_LEVEL_DEBUG = 'DEBUG';

    // Database drivers
    public const DRIVER_MYSQL = 'mysql';
    public const DRIVER_MYSQLI = 'mysqli';
    public const DRIVER_MARIADB = 'mariadb';
    public const DRIVER_ORACLE = 'oci';
    public const DRIVER_PDO_MYSQL = 'pdo';
    public const DRIVER_PDO_OCI = 'pdo_oci';
    public const DRIVER_CODEIGNITER3_MYSQL = 'codeigniter3';
    public const DRIVER_CODEIGNITER3_OCI = 'codeigniter3_oci';

    // Default values
    public const DEFAULT_CHUNK_SIZE = 1000;
    public const MIN_CHUNK_SIZE = 100;
    public const MAX_CHUNK_SIZE = 50000;

    // Parallel Constants
    public const DEFAULT_PARALLEL_ENABLED = FALSE;
    public const DEFAULT_PARALLEL_THREADS = 1;

    // Parallel Processing Constants
    public const PARALLEL_MIN_THREADS = 1;
    public const PARALLEL_MAX_THREADS = 32;
    public const PARALLEL_TIMEOUT = 43200; // 12 hours in seconds
    public const PARALLEL_MIN_ROWS_PER_THREAD = 1000;
    public const PARALLEL_FAILURE_THRESHOLD = 0.25; // 25%
    public const PARALLEL_MAX_RETRIES = 3;
    public const PARALLEL_RETRY_DELAY = 100000; // 100ms in microseconds
    public const PARALLEL_PIPE_TIMEOUT = 5; // seconds
    public const PARALLEL_PROCESS_CHECK_INTERVAL = 100000; // 100ms in microseconds
}
