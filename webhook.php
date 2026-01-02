<?php

require_once __DIR__ . '/includes/router.php';

['config' => $config, 'state' => $state] = sparta_bootstrap();
sparta_handle_request('webhook', $state, $config);
