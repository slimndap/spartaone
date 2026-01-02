<?php

require_once __DIR__ . '/includes/router.php';

['config' => $config, 'state' => $state] = sparta_bootstrap();
$action = $_GET['action'] ?? 'home';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($requestPath && preg_match('~/webhook/?$~', $requestPath)) {
    $action = 'webhook';
}

sparta_handle_request($action, $state, $config);
