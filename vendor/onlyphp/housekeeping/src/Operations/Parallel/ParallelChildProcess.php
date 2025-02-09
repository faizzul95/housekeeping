<?php

namespace OnlyPHP\Housekeeping\Operations\Parallel;

use OnlyPHP\Housekeeping\Constants\ArchiverConstants;
use OnlyPHP\Housekeeping\Utils\Logger;
use Exception;
use RuntimeException;

class ParallelChildProcess
{
    private $logger;
    private $threadId;
    private $pipe;

    public function __construct(Logger $logger, int $threadId, $pipe)
    {
        $this->logger = $logger;
        $this->threadId = $threadId;
        $this->pipe = $pipe;
    }

    public function execute(int $startId, int $endId, callable $processor): void
    {
        try {
            $this->setup();

            // Process the assigned range
            $result = $processor($startId, $endId);

            $response = [
                'status' => 'success',
                'thread' => $this->threadId,
                'data' => $result,
                'memory' => memory_get_peak_usage(true),
                'time' => microtime(true)
            ];
        } catch (Exception $e) {
            $response = [
                'status' => 'error',
                'thread' => $this->threadId,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'data' => ['backup' => 0, 'purge' => 0],
                'memory' => memory_get_peak_usage(true),
                'time' => microtime(true)
            ];
        }

        $this->sendResponse($response);
        exit($response['status'] === 'success' ? 0 : 1);
    }

    private function setup(): void
    {
        restore_error_handler();
        restore_exception_handler();

        while (ob_get_level()) {
            ob_end_clean();
        }

        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_signal(SIGINT, SIG_DFL);
    }

    private function sendResponse(array $response): void
    {
        $retry = 0;

        while ($retry < ArchiverConstants::PARALLEL_MAX_RETRIES) {
            try {
                $encoded = json_encode($response);
                if ($encoded === false) {
                    throw new RuntimeException("JSON encode failed: " . json_last_error_msg());
                }

                fwrite($this->pipe, $encoded);
                break;
            } catch (Exception $e) {
                $retry++;
                if ($retry === ArchiverConstants::PARALLEL_MAX_RETRIES) {
                    $this->logger->log(
                        "Failed to send response after " . ArchiverConstants::PARALLEL_MAX_RETRIES . " attempts: " . $e->getMessage(),
                        ArchiverConstants::LOG_LEVEL_WARNING
                    );
                }
                usleep(ArchiverConstants::PARALLEL_RETRY_DELAY);
            }
        }

        fclose($this->pipe);
    }
}
