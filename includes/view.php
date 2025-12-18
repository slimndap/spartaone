<?php

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

    extract($data);
    include __DIR__ . '/../views/layout.php';
}
