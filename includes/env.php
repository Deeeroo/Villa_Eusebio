<?php

function ve_project_root(): string {
    return dirname(__DIR__);
}

function ve_load_env(): void {
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = ve_project_root() . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }

        if (
            strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function ve_env(string $key, string $default = ''): string {
    ve_load_env();
    $value = getenv($key);
    return $value === false ? $default : (string)$value;
}

function ve_env_bool(string $key, bool $default = false): bool {
    $value = strtolower(trim(ve_env($key, $default ? 'true' : 'false')));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}
