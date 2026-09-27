<?php

function ve_base_path(): string {
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $configured = getenv('APP_BASE_PATH');
    if ($configured !== false) {
        $configured = trim((string)$configured);
        $basePath = $configured === '' ? '' : '/' . trim($configured, '/');
        return $basePath;
    }

    $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(/capstone_system)(/|$)#', $scriptName, $matches)) {
        $basePath = $matches[1];
        return $basePath;
    }

    $basePath = '';
    return $basePath;
}

function ve_url(string $path = ''): string {
    $path = trim($path);

    if ($path === '') {
        return ve_base_path() ?: '/';
    }

    if (
        preg_match('#^(https?:)?//#i', $path) ||
        str_starts_with($path, '#') ||
        str_starts_with($path, 'mailto:') ||
        str_starts_with($path, 'tel:')
    ) {
        return $path;
    }

    return ve_base_path() . '/' . ltrim($path, '/');
}
