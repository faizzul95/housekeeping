<?php

namespace OnlyPHP\Housekeeping\Operations\Parallel;

use OnlyPHP\Housekeeping\Constants\ArchiverConstants;
use OnlyPHP\Housekeeping\Utils\Logger;
use RuntimeException;
use Exception;

class ParallelResultCollector
{
    private $logger;
    private $totalBackup = 0;
    private $totalPurge = 0;
    private $failedProcesses = [];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function collect(array $processes, array $pipes): array
    {
        $startTime = time();
        $completed = [];

        while (count($completed) < count($processes)) {
            if (time() - $startTime > ArchiverConstants::PARALLEL_TIMEOUT) {
                throw new RuntimeException("Parallel processing timeout after " . ArchiverConstants::PARALLEL_TIMEOUT . " seconds");
            }

            foreach ($processes as $pid) {
                if (in_array($pid, $completed)) {
                    continue;
                }

                $status = null;
                $res = pcntl_waitpid($pid, $status, WNOHANG);

                if ($res === $pid) {
                    $completed[] = $pid;
                    $exitCode = pcntl_wexitstatus($status);

                    try {
                        $this->processResult($pipes[$pid], $exitCode);
                    } catch (Exception $e) {
                        $this->logger->log(
                            "Error processing result from PID $pid: " . $e->getMessage(),
                            ArchiverConstants::LOG_LEVEL_ERROR
                        );
                        $this->failedProcesses[] = $pid;
                    }

                    fclose($pipes[$pid]['pipe']);
                    unset($pipes[$pid]);
                }
            }

            usleep(ArchiverConstants::PARALLEL_PROCESS_CHECK_INTERVAL);
        }

        $this->handleFailedProcesses(count($processes));

        return [
            'backup' => $this->totalBackup,
            'purge' => $this->totalPurge
        ];
    }

    private function processResult(array $pipeInfo, int $exitCode): void
    {
        $pipe = $pipeInfo['pipe'];
        $thread = $pipeInfo['thread'];
        $range = $pipeInfo['range'];

        stream_set_timeout($pipe, ArchiverConstants::PARALLEL_PIPE_TIMEOUT);

        $data = stream_get_contents($pipe);
        if ($data === false) {
            throw new RuntimeException("Failed to read from pipe for thread $thread");
        }

        $result = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON from thread $thread: " . json_last_error_msg());
        }

        if ($exitCode !== 0 || $result['status'] === 'error') {
            $message = $result['message'] ?? "Unknown error";
            $this->logger->log(
                "Thread $thread failed (range: {$range['start']}-{$range['end']}): $message",
                ArchiverConstants::LOG_LEVEL_ERROR
            );
            $this->failedProcesses[] = $thread;
        }

        if (isset($result['data']['backup'], $result['data']['purge'])) {
            $this->totalBackup += $result['data']['backup'];
            $this->totalPurge += $result['data']['purge'];
        }

        if (isset($result['memory'], $result['time'])) {
            $memoryMB = round($result['memory'] / 1024 / 1024, 2);
            $this->logger->log(
                "Thread $thread completed: Memory peak: {$memoryMB}MB",
                ArchiverConstants::LOG_LEVEL_DEBUG
            );
        }
    }

    private function handleFailedProcesses(int $totalProcesses): void
    {
        if (!empty($this->failedProcesses)) {
            $failedCount = count($this->failedProcesses);
            $this->logger->log(
                "$failedCount of $totalProcesses processes failed. Check logs for details.",
                ArchiverConstants::LOG_LEVEL_WARNING
            );

            if ($failedCount / $totalProcesses > ArchiverConstants::PARALLEL_FAILURE_THRESHOLD) {
                throw new RuntimeException(
                    "Critical: More than " . (ArchiverConstants::PARALLEL_FAILURE_THRESHOLD * 100) . "% of parallel processes failed"
                );
            }
        }
    }
}
