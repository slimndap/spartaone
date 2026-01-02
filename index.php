<?php

require_once __DIR__ . '/includes/router.php';

['config' => $config, 'state' => $state] = sparta_bootstrap();
$action = $_GET['action'] ?? 'home';

sparta_handle_request($action, $state, $config);
