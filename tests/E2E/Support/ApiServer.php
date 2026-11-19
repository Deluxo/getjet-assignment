<?php

declare(strict_types=1);

namespace App\Tests\E2E\Support;

use RuntimeException;

/**
 * Boots the app with PHP's built-in server on a free port so E2E tests can
 * hit it over real HTTP. The server is torn down again on {@see self::stop()}.
 */
final class ApiServer
{
    /** @var resource|null */
    private $process;

    private int $port;

    private string $baseUrl = '';

    private string $logPath;

    public function __construct(
        private readonly string $projectDir,
        private readonly int $bootTimeout = 15,
    ) {
        $this->logPath = $projectDir . '/var/log/e2e-server.log';
    }

    public function start(): void
    {
        $this->port = $this->pickFreePort();
        $this->baseUrl = sprintf('http://127.0.0.1:%d', $this->port);

        $this->ensureLogDir();

        $truncate = fopen($this->logPath, 'w');
        fclose($truncate);

        $command = [PHP_BINARY, '-S', sprintf('127.0.0.1:%d', $this->port), '-t', $this->projectDir . '/public'];

        $logFile = fopen($this->logPath, 'ab');

        $this->process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => $logFile,
            2 => $logFile,
        ], $pipes, $this->projectDir, $this->environment());

        if ($this->process === false) {
            throw new RuntimeException('Failed to start the API server process.');
        }

        $this->waitUntilReady();
        $this->registerShutdownHook();
    }

    public function stop(): void
    {
        if ($this->process === null) {
            return;
        }

        proc_terminate($this->process);

        for ($i = 0; $i < 50; $i++) {
            $status = proc_get_status($this->process);

            if (!($status['running'] ?? false)) {
                break;
            }

            usleep(100_000);
        }

        proc_close($this->process);
        $this->process = null;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Tail of the server log, useful for failure messages when a request 5xx'd.
     */
    public function logTail(int $lines = 30): string
    {
        if (!is_file($this->logPath)) {
            return '(no server log available)';
        }

        $all = file($this->logPath, FILE_IGNORE_NEW_LINES) ?: [];

        return implode("\n", array_slice($all, -$lines));
    }

    private function pickFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            throw new RuntimeException(sprintf('Failed to allocate a free port: %s', $errstr));
        }

        $name = stream_socket_get_name($socket, false);
        $port = (int) explode(':', $name)[1] ?? 0;
        fclose($socket);

        return $port;
    }

    private function waitUntilReady(): void
    {
        $deadline = microtime(true) + $this->bootTimeout;

        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.2);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(100_000);
        }

        throw new RuntimeException(
            sprintf('API server did not become ready in time. %s', $this->logTail()),
        );
    }

    private function environment(): array
    {
        $env = getenv();

        return array_merge($env === false ? [] : $env, [
            'APP_ENV' => 'dev',
            'APP_DEBUG' => '1',
        ]);
    }

    private function ensureLogDir(): void
    {
        $dir = dirname($this->logPath);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Failed to create log directory "%s".', $dir));
        }
    }

    private function registerShutdownHook(): void
    {
        $server = $this;

        register_shutdown_function(static function () use ($server): void {
            $server->stop();
        });
    }
}
