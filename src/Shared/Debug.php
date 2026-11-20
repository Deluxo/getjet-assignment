<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Development helper: dumps values to stderr, which reaches the terminal in
 * every setup this project uses.
 *
 * - Unit / functional tests: PHPUnit's console.
 * - E2E tests: the spawned php -S stderr, streamed into PHPUnit by ApiServer.
 * - Running server: `docker logs shift-api`.
 *
 * Unlike var_dump()/print_r(), it never writes to the HTTP response body, so
 * it cannot corrupt headers or the payload.
 */
final class Debug
{
    public static function dump(mixed ...$values): mixed
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? [];
        $location = sprintf('%s:%d', $caller['file'] ?? 'unknown', $caller['line'] ?? 0);

        $text = '';

        foreach ($values as $value) {
            $text .= self::format($value) . PHP_EOL;
        }

        $line = sprintf('[%s] %s', $location, $text);

        if (false === file_put_contents('php://stderr', $line)) {
            error_log($line);
        }

        return $values;
    }

    private static function format(mixed $value): string
    {
        if ($value === null || \is_scalar($value)) {
            return var_export($value, true);
        }

        return print_r($value, true);
    }
}
