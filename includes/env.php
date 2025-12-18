<?php

/**
 * Load simple KEY=VALUE pairs from a .env file into getenv/$_ENV.
 *
 * @param string $path Absolute path to .env file.
 */
function sparta_load_env(string $path): void
{
    if (!is_readable($path)) {
        return;
    }
    $parsed = parse_ini_file($path, false, INI_SCANNER_RAW);
    if (!is_array($parsed)) {
        return;
    }
    foreach ($parsed as $key => $value) {
        // Respect existing environment overrides.
        if (getenv($key) !== false) {
            continue;
        }
        $value = (string)$value;
        putenv("$key=$value");
        $_ENV[$key] = $value;
        if (!array_key_exists($key, $_SERVER)) {
            $_SERVER[$key] = $value;
        }
    }
}

/**
 * Fetch an environment value with optional default.
 */
function sparta_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value !== false ? (string)$value : $default;
}
