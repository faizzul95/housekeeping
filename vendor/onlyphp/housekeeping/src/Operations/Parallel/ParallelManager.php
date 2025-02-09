<?php

namespace OnlyPHP\Housekeeping\Operations\Parallel;

use OnlyPHP\Housekeeping\Config\ArchiverConfig;
use OnlyPHP\Housekeeping\Constants\ArchiverConstants;
use OnlyPHP\Housekeeping\Utils\Logger;
use RuntimeException;
use Exception;

class ParallelManager
{
    private $logger;
    private $config;
    private $processes = [];
    private $pipes = [];

    public function __construct(Logger $logger, ArchiverConfig $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function execute(array $range, callable $processor): array
    {
        try {
            $this->validateRequirements();

            $actualThreads = $this->calculateOptimalThreadCount($range);
            $chunkSize = $this->calculateChunkSize($range, $actualThreads);

            $this->setupSignalHandlers();

            for ($i = 0; $i < $actualThreads; $i++) {
                $startId = $range['min'] + ($i * $chunkSize);
                $endId = min($startId + $chunkSize - 1, $range['max']);
                $this->startChildProcess($i, $startId, $endId, $processor);
            }

            $resultCollector = new ParallelResultCollector($this->logger);
            return $resultCollector->collect($this->processes, $this->pipes);
        } catch (Exception $e) {
            $this->logger->log("Parallel execution failed: " . $e->getMessage(), ArchiverConstants::LOG_LEVEL_ERROR);
            $this->killAllChildren();
            throw $e;
        }
    }

    private function validateRequirements(): void
    {
        if (!extension_loaded('pcntl')) {
            throw new RuntimeException("PCNTL extension is required for parallel processing");
        }

        if (!extension_loaded('posix')) {
            throw new RuntimeException("POSIX extension is required for parallel processing");
        }

        $memoryLimit = ini_get('memory_limit');
        if (intval($memoryLimit) < 256) {
            $this->logger->log(
                "Warning: Low memory limit ($memoryLimit) may affect parallel processing",
                ArchiverConstants::LOG_LEVEL_WARNING
            );
        }
    }

    private function calculateOptimalThreadCount(array $range): int
    {
        $rangeSize = $range['max'] - $range['min'] + 1;
        $requestedThreads = $this->config->getParallelThreads();

        $actualThreads = min(
            $requestedThreads,
            max(1, floor($rangeSize / ArchiverConstants::PARALLEL_MIN_ROWS_PER_THREAD))
        );

        if ($actualThreads < $requestedThreads) {
            $this->logger->log(
                "Reduced thread count from $requestedThreads to $actualThreads due to data size",
                ArchiverConstants::LOG_LEVEL_WARNING
            );
        }

        return $actualThreads;
    }

    private function calculateChunkSize(array $range, int $threads): int
    {
        $rangeSize = $range['max'] - $range['min'] + 1;
        return (int)ceil($rangeSize / $threads);
    }

    private function setupSignalHandlers(): void
    {
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
    }

    private function startChildProcess(int $threadId, int $startId, int $endId, callable $processor): void
    {
        $pipe = $this->createPipe($threadId);
        $pid = pcntl_fork();

        if ($pid == -1) {
            throw new RuntimeException("Failed to fork process for thread $threadId");
        }

        if ($pid) {
            // Parent process
            fclose($pipe[1]);
            $this->pipes[$pid] = [
                'pipe' => $pipe[0],
                'thread' => $threadId,
                'range' => ['start' => $startId, 'end' => $endId]
            ];
            $this->processes[] = $pid;
        } else {
            // Child process
            $childProcess = new ParallelChildProcess($this->logger, $threadId, $pipe[1]);
            $childProcess->execute($startId, $endId, $processor);
        }
    }

    private function createPipe(int $threadId)
    {
        $pipe = @stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if (!$pipe) {
            throw new RuntimeException("Failed to create IPC pipe for thread $threadId");
        }

        stream_set_blocking($pipe[0], false);
        stream_set_blocking($pipe[1], false);

        return $pipe;
    }

    private function killAllChildren(): void
    {
        foreach ($this->processes as $pid) {
            if (posix_kill($pid, 0)) {
                posix_kill($pid, SIGTERM);
                usleep(ArchiverConstants::PARALLEL_PROCESS_CHECK_INTERVAL);

                if (posix_kill($pid, 0)) {
                    posix_kill($pid, SIGKILL);
                }
            }
        }
    }

    public function handleSignal(int $signo): void
    {
        switch ($signo) {
            case SIGTERM:
            case SIGINT:
                $this->logger->log("Received termination signal", ArchiverConstants::LOG_LEVEL_WARNING);
                throw new RuntimeException("Process interrupted by signal $signo");
        }
    }
}
