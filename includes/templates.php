<?php

/**
 * Render a view partial by name with provided variables.
 *
 * @param string $name Partial path relative to /views (with or without .php).
 * @param array<string,mixed> $vars Variables to extract into the partial scope.
 * @throws RuntimeException when the partial cannot be found or read.
 */
function render_partial(string $name, array $vars = []): void
{
    $path = __DIR__ . '/../views/' . ltrim($name, '/');
    if (!str_ends_with($path, '.php')) {
        $path .= '.php';
    }
    if (!is_readable($path)) {
        throw new RuntimeException("Partial not found: {$name}");
    }
    if ($vars) {
        extract($vars, EXTR_SKIP);
    }
    include $path;
}
