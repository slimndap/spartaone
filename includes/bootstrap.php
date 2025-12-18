<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/training.php';
require_once __DIR__ . '/roles.php';
require_once __DIR__ . '/paces.php';
require_once __DIR__ . '/goal-render.php';
require_once __DIR__ . '/strava.php';
require_once __DIR__ . '/view.php';

/**
 * Bootstraps configuration, env, session, and Strava-derived state.
 *
 * @return array{config: array<string,string>, state: array<string,mixed>} Config values plus hydrated state.
 */
function sparta_bootstrap(): array
{
    session_start(); // Ensure session is ready before buildStravaState reads/writes.

    $envFile = __DIR__ . '/../.env';
    sparta_load_env($envFile); // Load .env into getenv/$_ENV/$_SERVER if present.

    $config = [
        'clientId' => sparta_env('STRAVA_CLIENT_ID'),
        'clientSecret' => sparta_env('STRAVA_CLIENT_SECRET'),
        'redirectUri' => sparta_env('STRAVA_REDIRECT_URI'),
        'authBase' => 'https://www.strava.com/oauth/authorize',
        'tokenUrl' => 'https://www.strava.com/api/v3/oauth/token',
        'scope' => 'read,activity:read',
        'athletesDir' => __DIR__ . '/../data/athletes',
        'goalsDir' => __DIR__ . '/../data/goals',
        'trainingsDir' => __DIR__ . '/../data/trainings',
        'settingsDir' => __DIR__ . '/../data/settings',
    ];

    // Build auth/state context from Strava helpers and current request.
    $state = buildStravaState($_GET, $_POST, $_SESSION, $config);
    $settings = $state['athleteSettings'] ?? [];
    $state['tempoPaces'] = resolve_tempo_paces(
        $settings,
        $state['currentAthleteId'] ?? null,
        $config['settingsDir'],
        true
    );

    return ['config' => $config, 'state' => $state];
}
