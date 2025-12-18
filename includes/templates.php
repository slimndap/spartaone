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
    // PHP 8 polyfill for str_ends_with to keep compatibility on older runtimes.
    $endsWithPhp = function (string $haystack, string $needle): bool {
        $len = strlen($needle);
        if ($len === 0) {
            return true;
        }
        return substr($haystack, -$len) === $needle;
    };
    if (!$endsWithPhp($path, '.php')) {
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

/**
 * Render a view inside the shared layout.
 *
 * @param string $view Name of the view file (without .php) inside /views.
 * @param array<string, mixed> $data Data to extract for use inside the view.
 */
function render(string $view, array $data = []): void
{
    $viewPath = __DIR__ . '/../views/' . $view . '.php';
    if (!file_exists($viewPath)) {
        http_response_code(500);
        echo 'View not found.';
        return;
    }

    if ($data) {
        extract($data);
    }
    include __DIR__ . '/../views/layout.php';
}
