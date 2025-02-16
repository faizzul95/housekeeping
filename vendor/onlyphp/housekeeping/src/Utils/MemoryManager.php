<?php

namespace OnlyPHP\Housekeeping\Utils;

use OnlyPHP\Housekeeping\Constants\ArchiverConstants;

class MemoryManager
{
    public static function getMemoryLimitInBytes()
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int)substr($memoryLimit, 0, -1);

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int)$memoryLimit,
        };
    }

    public static function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }
        return round($bytes, 2) . ' ' . $units[$index];
    }

    public static function checkMemoryUsage($config, $logger)
    {
        $currentMemory = memory_get_usage(true);
        $memoryUsed = $currentMemory - $config->getStartMemory();
        $memoryThreshold = $config->getMemoryLimit() * 0.9; // 90% of memory limit

        if ($currentMemory > $memoryThreshold) {
            $logger->log("High memory usage detected: " . self::formatBytes($memoryUsed), ArchiverConstants::LOG_LEVEL_WARNING);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            sleep(1);
        }
    }
}
