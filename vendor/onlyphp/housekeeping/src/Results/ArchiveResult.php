<?php

namespace OnlyPHP\Housekeeping\Results;

use OnlyPHP\Housekeeping\Utils\MemoryManager;
use InvalidArgumentException, RuntimeException, Exception;

class ArchiveResult
{
    public static function createEmpty($originalTable, $archiveTable, $startTime, $mode)
    {
        $result = [
            'status' => 'completed',
            'mode' => $mode,
            'table' => $originalTable,
            'backup_table' => $archiveTable,
            'total' => 0,
            'processed' => [
                'backup' => 0,
                'purge' => 0,
            ],
            'messages' => 'No records found to process',
            'execution_date' => date('Y-m-d H:i:s'),
            'execution_time' => self::calculateRuntime($startTime, microtime(true))
        ];

        return $result;
    }

    public static function createComplete($originalTable, $archiveTable, $startTime, $processedCount, $totalRecords, $initialMemory, $threads, $mode)
    {
        $endMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        return [
            'status' => 'completed',
            'mode' => $mode,
            'table' => $originalTable,
            'backup_table' => $archiveTable,
            'total' => $totalRecords,
            'processed' => $processedCount,
            'messages' => 'Records processed successfully',
            'execution_date' => date('Y-m-d H:i:s'),
            'execution_time' => self::calculateRuntime($startTime, microtime(true)),
            'threads' => $threads,
            'memory' => [
                'initial' => MemoryManager::formatBytes($initialMemory),
                'final' => MemoryManager::formatBytes($endMemory),
                'peak' => MemoryManager::formatBytes($peakMemory),
                'used' => MemoryManager::formatBytes($endMemory - $initialMemory)
            ]
        ];
    }

    public static function calculateRuntime($startTime, $endTime)
    {
        $runtime = $endTime - $startTime;
        return self::formatRuntime($runtime);
    }

    public static function formatRuntime($seconds)
    {
        try {
            // Input validation
            if (!is_numeric($seconds)) {
                throw new InvalidArgumentException('Input must be a numeric value');
            }

            // Ensure we have a positive number
            $seconds = abs((float) $seconds);

            // Calculate days
            $days = floor($seconds / 86400);  // 86400 = 24 * 60 * 60
            $remainderDays = fmod($seconds, 86400);

            // Calculate hours
            $hours = floor($remainderDays / 3600);
            $remainderHours = fmod($remainderDays, 3600);

            // Calculate minutes
            $minutes = floor($remainderHours / 60);
            $remainderMinutes = fmod($remainderHours, 60);

            // Seconds and milliseconds
            $seconds = $remainderMinutes;

            // Prevent potential floating point precision issues
            if ($seconds >= 60) {
                $seconds = 59.999;
            }

            // Handle extreme values
            if ($days > 999999) {
                throw new RuntimeException('Duration too large to display');
            }

            // Format the output
            if ($days > 0) {
                $formatted = sprintf('%dd %02d:%02d:%06.3f', $days, $hours, $minutes, $seconds);
            } else {
                $formatted = sprintf('%02d:%02d:%06.3f', $hours, $minutes, $seconds);
            }

            return $formatted;
        } catch (Exception $e) {
            // throw $e;
            return '00:00:00.000';
        }
    }
}
