<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$database = $root.'/var/test.db';

if (is_file($database)) {
    unlink($database);
}

$command = sprintf(
    '%s %s doctrine:migrations:migrate --env=test --no-interaction 2>&1',
    escapeshellarg(PHP_BINARY),
    escapeshellarg($root.'/bin/console'),
);

exec($command, $output, $exitCode);

if (0 !== $exitCode) {
    fwrite(STDERR, "Could not prepare the test database:\n".implode("\n", $output)."\n");
    exit(1);
}
