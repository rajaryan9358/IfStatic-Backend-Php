<?php

declare(strict_types=1);

function parseDotEnv(string $path): array {
    if (!is_file($path)) {
        return [];
    }

    $vars = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eqPos));
        $val = trim(substr($line, $eqPos + 1));

        if ($key === '') {
            continue;
        }

        // Strip optional quotes
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }

        $vars[$key] = $val;
    }

    return $vars;
}

$env = parseDotEnv(__DIR__ . '/../.env');
$portRaw = $env['PORT'] ?? getenv('PORT') ?: '5000';
$port = (int)$portRaw;
if ($port <= 0 || $port > 65535) {
    $port = 5000;
}

$publicDir = realpath(__DIR__ . '/../public') ?: (__DIR__ . '/../public');
$cmd = sprintf('php -S localhost:%d -t %s', $port, escapeshellarg($publicDir));

fwrite(STDERR, "Starting Slim API on http://localhost:{$port}\n");

passthru($cmd, $exitCode);
exit((int)$exitCode);
