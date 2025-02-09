<?php

namespace OnlyPHP\Housekeeping\Utils;

use OnlyPHP\Housekeeping\Constants\ArchiverConstants;
use RuntimeException;

class Logger
{
    private $logPath;

    public function __construct($logPath)
    {
        $this->logPath = $logPath;
    }

    public function log($message, $level = ArchiverConstants::LOG_LEVEL_INFO)
    {
        $timestamp = date('Y-m-d H:i:s');
        $pid = function_exists('posix_getpid') ? posix_getpid() : 'main';
        $logMessage = "[{$timestamp}] [{$level}] [PID:{$pid}] {$message}" . PHP_EOL;

        $directory = dirname($this->logPath);

        if (!file_exists($directory)) {
            if (!mkdir($directory, 0777, true)) {
                throw new RuntimeException("Unable to create log directory: {$directory}");
            }
        }

        if (!is_writable($directory)) {
            throw new RuntimeException("Log directory is not writable: {$directory}");
        }

        file_put_contents($this->logPath, $logMessage, FILE_APPEND | LOCK_EX);
    }

    public function rotateLogIfNeeded()
    {
        if (file_exists($this->logPath) && filesize($this->logPath) > 50 * 1024 * 1024) { // 50MB
            rename($this->logPath, $this->logPath . '.' . date('Y-m-d-His'));
        }
    }
}
