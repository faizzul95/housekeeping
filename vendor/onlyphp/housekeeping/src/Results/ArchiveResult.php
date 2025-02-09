<?php

namespace OnlyPHP\Housekeeping\Results;

use OnlyPHP\Housekeeping\Utils\MemoryManager;

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

    private static function calculateRuntime($startTime, $endTime)
    {
        $runtime = $endTime - $startTime;
        return self::formatRuntime($runtime);
    }

    private static function formatRuntime($seconds)
    {
        $hours = floor($seconds / 3600);
        $remainderHours = fmod($seconds, 3600);

        $minutes = floor($remainderHours / 60);
        $remainderMinutes = fmod($remainderHours, 60);

        $seconds = $remainderMinutes;

        return sprintf('%02d:%02d:%06.3f', $hours, $minutes, $seconds);
    }
}
