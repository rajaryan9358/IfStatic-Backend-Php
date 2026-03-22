<?php

declare(strict_types=1);

use App\Database\Database;
use Dotenv\Dotenv;

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';

if (file_exists($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$prefix = null;
foreach ($argv as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: php bin/migrate.php [--prefix=YYYYMMDD]\n");
        exit(0);
    }
    if (str_starts_with($arg, '--prefix=')) {
        $prefix = substr($arg, strlen('--prefix='));
    }
}

$pdo = Database::connection();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS migrations (\n"
    . "  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n"
    . "  filename VARCHAR(255) NOT NULL UNIQUE,\n"
    . "  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP\n"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
);

$dir = $root . '/database/migrations';
$files = glob($dir . '/*.sql') ?: [];

sort($files, SORT_STRING);

if (is_string($prefix) && $prefix !== '') {
    $files = array_values(array_filter($files, static function (string $file) use ($prefix): bool {
        return str_starts_with(basename($file), $prefix);
    }));
}

$select = $pdo->prepare('SELECT 1 FROM migrations WHERE filename = ? LIMIT 1');
$insert = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');

$applied = 0;
$skipped = 0;

foreach ($files as $file) {
    $filename = basename($file);

    $select->execute([$filename]);
    if ($select->fetchColumn()) {
        $skipped++;
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Failed to read migration: {$filename}\n");
        exit(1);
    }

    $statements = splitSqlStatements($sql);

    fwrite(STDOUT, "Applying {$filename}...\n");

    try {
        foreach ($statements as $statement) {
            $trimmed = trim($statement);
            if ($trimmed === '') {
                continue;
            }
            $pdo->exec($trimmed);
        }

        $insert->execute([$filename]);
        $applied++;
    } catch (Throwable $e) {
        fwrite(STDERR, "Error applying {$filename}: {$e->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Done. Applied: {$applied}, Skipped: {$skipped}\n");

/**
 * Splits SQL into executable statements, ignoring semicolons inside strings/comments.
 *
 * @return list<string>
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';

    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : "\0";

        if ($inLineComment) {
            if ($ch === "\n") {
                $inLineComment = false;
                $buffer .= $ch;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($ch === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble && !$inBacktick) {
            if ($ch === '-' && $next === '-') {
                $prev = $i > 0 ? $sql[$i - 1] : " ";
                $after = $i + 2 < $len ? $sql[$i + 2] : " ";
                if (ctype_space($prev) && (ctype_space($after) || $after === "\0")) {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
            }

            if ($ch === '#') {
                $inLineComment = true;
                continue;
            }

            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($ch === "'" && !$inDouble && !$inBacktick) {
            if ($inSingle) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inSingle = false;
                }
            } else {
                $inSingle = true;
            }
            $buffer .= $ch;
            continue;
        }

        if ($ch === '"' && !$inSingle && !$inBacktick) {
            if ($inDouble) {
                $escaped = $i > 0 && $sql[$i - 1] === '\\';
                if (!$escaped) {
                    $inDouble = false;
                }
            } else {
                $inDouble = true;
            }
            $buffer .= $ch;
            continue;
        }

        if ($ch === '`' && !$inSingle && !$inDouble) {
            $inBacktick = !$inBacktick;
            $buffer .= $ch;
            continue;
        }

        if ($ch === ';' && !$inSingle && !$inDouble && !$inBacktick) {
            $statements[] = $buffer;
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    if (trim($buffer) !== '') {
        $statements[] = $buffer;
    }

    return $statements;
}
