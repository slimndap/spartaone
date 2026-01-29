<?php

if (!defined('SPARTA_STRAVA_REMEMBER_COOKIE')) {
    define('SPARTA_STRAVA_REMEMBER_COOKIE', 'sparta_strava_athlete_id');
}

/**
 * Exchange an authorization code or refresh token for Strava tokens.
 *
 * @param string $tokenUrl Token endpoint URL.
 * @param array<string, string|int> $payload Fields to POST to the token endpoint.
 * @return array<string, mixed> Array with 'tokens' on success or 'error' on failure.
 */
function requestTokens(string $tokenUrl, array $payload): array
{
    $ch = curl_init($tokenUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['error' => 'Token request failed: ' . $curlErr];
    }
    if ($httpCode >= 400) {
        return ['error' => 'Token request failed with status ' . $httpCode . ': ' . $response];
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Could not decode token response.'];
    }

    return ['tokens' => $decoded];
}

/**
 * Fetch latest athlete activities with a bearer access token.
 *
 * @param string $accessToken Short-lived access token from Strava.
 * @param int $perPage Number of activities to fetch.
 * @return array<string, mixed> Array with 'activities' on success or 'error' on failure.
 */
function fetchActivities(string $accessToken, int $perPage = 10): array
{
    $url = 'https://www.strava.com/api/v3/athlete/activities?per_page=' . $perPage;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['error' => 'Fetching activities failed: ' . $curlErr];
    }
    if ($httpCode >= 400) {
        return ['error' => 'Fetching activities failed with status ' . $httpCode . ': ' . $body];
    }

    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Could not decode activities response.'];
    }

    return ['activities' => $decoded];
}

/**
 * Filter activities to supported sport types.
 *
 * @param array<int,array<string,mixed>> $activities
 * @return array<int,array<string,mixed>>
 */
function sparta_filter_activities(array $activities): array
{
    $allowed = ['Run', 'TrailRun'];
    $filtered = [];
    foreach ($activities as $activity) {
        $sportType = (string)($activity['sport_type'] ?? $activity['type'] ?? '');
        if ($sportType === '' || !in_array($sportType, $allowed, true)) {
            continue;
        }
        $filtered[] = $activity;
    }
    return $filtered;
}

/**
 * Fetch laps for a specific activity.
 *
 * @param string $accessToken Short-lived access token from Strava.
 * @param int|string $activityId Activity ID to fetch laps for.
 * @return array<string, mixed> Array with 'laps' on success or 'error' on failure.
 */
function fetchActivityLaps(string $accessToken, $activityId): array
{
    if ($activityId === '' || $activityId === null) {
        return ['error' => 'Missing activity id.'];
    }
    $url = 'https://www.strava.com/api/v3/activities/' . rawurlencode((string)$activityId) . '/laps';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['error' => 'Fetching laps failed: ' . $curlErr];
    }
    if ($httpCode >= 400) {
        return ['error' => 'Fetching laps failed with status ' . $httpCode . ': ' . $body];
    }

    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Could not decode laps response.'];
    }

    return ['laps' => $decoded];
}

/**
 * Format a duration in seconds as H:MM:SS or M:SS.
 */
function sparta_format_duration(?int $seconds): string
{
    if ($seconds === null) {
        return '';
    }
    $seconds = max(0, (int)$seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%d:%02d', $minutes, $secs);
}

/**
 * Fetch the authenticated athlete profile.
 *
 * @param string $accessToken Short-lived access token from Strava.
 * @return array<string, mixed> Array with 'athlete' on success or 'error' on failure.
 */
function fetchAthlete(string $accessToken): array
{
    $url = 'https://www.strava.com/api/v3/athlete';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['error' => 'Fetching athlete failed: ' . $curlErr];
    }
    if ($httpCode >= 400) {
        return ['error' => 'Fetching athlete failed with status ' . $httpCode . ': ' . $body];
    }

    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Could not decode athlete response.'];
    }

    return ['athlete' => $decoded];
}

/**
 * Load athlete records from per-athlete JSON files.
 *
 * @param string $dir Base directory for athlete files.
 * @return array<int, array<string,mixed>>
 */
function loadAthletes(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $athletes = [];
    $files = glob(rtrim($dir, '/\\') . '/*.json') ?: [];
    foreach ($files as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $contents = file_get_contents($file);
        if ($contents === false || $contents === '') {
            continue;
        }
        $decoded = json_decode($contents, true);
        if (is_array($decoded) && isset($decoded['id'])) {
            $athletes[] = $decoded;
        }
    }
    return $athletes;
}

/**
 * Save a single athlete record to its own JSON file.
 *
 * @param string $dir
 * @param array<string,mixed> $athlete
 * @return bool
 */
function saveAthleteRecord(string $dir, array $athlete): bool
{
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
    }
    if (empty($athlete['id'])) {
        return false;
    }
    $json = json_encode($athlete, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    $path = rtrim($dir, '/\\') . '/' . $athlete['id'] . '.json';
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

/**
 * Store a webhook event payload to disk for later processing.
 *
 * @param string $dir
 * @param array<string,mixed> $event
 * @return bool
 */
function sparta_store_webhook_event(string $dir, array $event): bool
{
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
    }

    $payload = $event['payload'] ?? [];
    $suffixParts = [];
    if (is_array($payload)) {
        if (!empty($payload['object_type'])) {
            $suffixParts[] = (string)$payload['object_type'];
        }
        if (!empty($payload['object_id'])) {
            $suffixParts[] = (string)$payload['object_id'];
        }
    }
    $suffix = $suffixParts ? implode('_', $suffixParts) : 'event';

    try {
        $entropy = bin2hex(random_bytes(4));
    } catch (Exception $e) {
        $entropy = uniqid('', false);
    }

    $filename = date('Ymd_His') . '_' . $suffix . '_' . $entropy . '.json';
    $path = rtrim($dir, '/\\') . '/' . $filename;
    $json = json_encode($event, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

/**
 * Save latest activities for an athlete.
 *
 * @param string $dir
 * @param int|string $athleteId
 * @param array<int,array<string,mixed>> $activities
 * @return bool
 */
function sparta_save_athlete_activities(string $dir, $athleteId, array $activities): bool
{
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
    }
    if ($athleteId === '' || $athleteId === null) {
        return false;
    }
    $payload = [
        'updated_at' => date('c'),
        'activities' => $activities,
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    $path = rtrim($dir, '/\\') . '/' . $athleteId . '.json';
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

/**
 * Load cached activities for an athlete.
 *
 * @param string $dir
 * @param int|string $athleteId
 * @return array{activities: array<int,array<string,mixed>>, updated_at: string|null}
 */
function sparta_load_athlete_activities(string $dir, $athleteId): array
{
    $empty = ['activities' => [], 'updated_at' => null];
    if ($athleteId === '' || $athleteId === null) {
        return $empty;
    }
    $path = rtrim($dir, '/\\') . '/' . $athleteId . '.json';
    if (!is_readable($path)) {
        return $empty;
    }
    $contents = file_get_contents($path);
    if ($contents === false || $contents === '') {
        return $empty;
    }
    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        return $empty;
    }
    if (isset($decoded['activities']) && is_array($decoded['activities'])) {
        return [
            'activities' => $decoded['activities'],
            'updated_at' => isset($decoded['updated_at']) ? (string)$decoded['updated_at'] : null,
        ];
    }
    $keys = array_keys($decoded);
    $isList = $keys === range(0, count($decoded) - 1);
    if ($isList) {
        return ['activities' => $decoded, 'updated_at' => null];
    }
    return $empty;
}

/**
 * Save tokens for an athlete to disk.
 *
 * @param string $dir
 * @param int|string $athleteId
 * @param array<string,mixed> $tokens
 * @return bool
 */
function sparta_save_athlete_tokens(string $dir, $athleteId, array $tokens): bool
{
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
    }
    if ($athleteId === '' || $athleteId === null) {
        return false;
    }
    $payload = [
        'updated_at' => date('c'),
        'tokens' => $tokens,
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    $path = rtrim($dir, '/\\') . '/' . $athleteId . '.json';
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

/**
 * Load stored tokens for an athlete.
 *
 * @param string $dir
 * @param int|string $athleteId
 * @return array{tokens: array<string,mixed>|null, updated_at: string|null}
 */
function sparta_load_athlete_tokens(string $dir, $athleteId): array
{
    $empty = ['tokens' => null, 'updated_at' => null];
    if ($athleteId === '' || $athleteId === null) {
        return $empty;
    }
    $path = rtrim($dir, '/\\') . '/' . $athleteId . '.json';
    if (!is_readable($path)) {
        return $empty;
    }
    $contents = file_get_contents($path);
    if ($contents === false || $contents === '') {
        return $empty;
    }
    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        return $empty;
    }
    if (isset($decoded['tokens']) && is_array($decoded['tokens'])) {
        return [
            'tokens' => $decoded['tokens'],
            'updated_at' => isset($decoded['updated_at']) ? (string)$decoded['updated_at'] : null,
        ];
    }
    return $empty;
}

/**
 * Delete stored tokens for an athlete.
 *
 * @param string $dir
 * @param int|string $athleteId
 * @return bool
 */
function sparta_delete_athlete_tokens(string $dir, $athleteId): bool
{
    if ($athleteId === '' || $athleteId === null) {
        return true;
    }
    $path = rtrim($dir, '/\\') . '/' . $athleteId . '.json';
    if (!file_exists($path)) {
        return true;
    }
    return unlink($path);
}

/**
 * Manage the remember-me cookie for persisted Strava logins.
 *
 * @param string|null $athleteId
 * @param int $lifetime
 * @param bool $secure
 * @return void
 */
function sparta_set_remembered_athlete(?string $athleteId, int $lifetime, bool $secure): void
{
    $expires = $athleteId ? (time() + $lifetime) : (time() - 3600);
    $value = $athleteId ? (string)$athleteId : '';
    setcookie(SPARTA_STRAVA_REMEMBER_COOKIE, $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if ($athleteId) {
        $_COOKIE[SPARTA_STRAVA_REMEMBER_COOKIE] = $value;
    } else {
        unset($_COOKIE[SPARTA_STRAVA_REMEMBER_COOKIE]);
    }
}

/**
 * Handle Strava webhook validation and event delivery.
 *
 * @param array<string,mixed> $state
 * @param array<string,string> $config
 * @param string $action
 */
function sparta_handle_webhook(array $state, array $config, string $action): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $verifyToken = $config['webhookVerifyToken'] ?? '';

    if ($method === 'GET') {
        $challenge = (string)($_GET['hub.challenge'] ?? $_GET['hub_challenge'] ?? '');
        $token = (string)($_GET['hub.verify_token'] ?? $_GET['hub_verify_token'] ?? '');

        if ($verifyToken === '' || $token === '' || !hash_equals($verifyToken, $token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid verify token']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode(['hub.challenge' => $challenge]);
        return;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $raw = file_get_contents('php://input');
    $payload = [];
    $error = null;

    if ($raw === false || $raw === '') {
        $error = 'Empty payload';
    } else {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        } else {
            $error = 'Invalid JSON payload';
        }
    }

    $event = [
        'received_at' => date('c'),
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'payload' => $payload,
        'raw' => $raw === false ? '' : $raw,
        'error' => $error,
    ];

    $webhooksDir = $config['webhooksDir'] ?? '';
    if ($webhooksDir) {
        sparta_store_webhook_event($webhooksDir, $event);
    }

    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
}

/**
 * Load goal records from per-athlete JSON files.
 *
 * @param string $dir Base directory for goal files.
 * @return array<string,array<int,array<string,string>>>
 */
function normalize_goal_date(string $date): string
{
    $date = trim($date);
    if ($date === '') {
        return '';
    }
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return '';
    }
    return $parsed->format('Y-m-d');
}

/**
 * Normalize a goal record from legacy (string) or structured formats.
 *
 * @param array<string,mixed>|string $rawGoal
 * @return array<string,string>
 */
function normalize_goal_entry($rawGoal): array
{
    $description = '';
    $targetDate = '';
    $id = '';
    $createdAt = '';

    if (is_string($rawGoal)) {
        $description = $rawGoal;
    } elseif (is_array($rawGoal)) {
        $description = $rawGoal['description'] ?? ($rawGoal['goal'] ?? '');
        $targetDate = $rawGoal['target_date'] ?? ($rawGoal['date'] ?? '');
        $id = $rawGoal['id'] ?? '';
        $createdAt = $rawGoal['created_at'] ?? '';
    }

    $description = trim((string)$description);
    $targetDate = normalize_goal_date((string)$targetDate);

    if ($id === '') {
        try {
            $id = 'goal_' . bin2hex(random_bytes(5));
        } catch (Exception $e) {
            $id = 'goal_' . uniqid('', false);
        }
    }
    if (!is_string($createdAt) || strtotime($createdAt) === false) {
        $createdAt = date('c');
    }

    return [
        'id' => $id,
        'description' => $description,
        'target_date' => $targetDate,
        'created_at' => $createdAt,
    ];
}

function loadGoals(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }
    $goals = [];
    $files = glob(rtrim($dir, '/\\') . '/*.json') ?: [];
    foreach ($files as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $contents = file_get_contents($file);
        if ($contents === false || $contents === '') {
            continue;
        }
        $decoded = json_decode($contents, true) ?: [];
        $rawGoals = $decoded;
        if (is_array($decoded) && array_key_exists('goals', $decoded) && is_array($decoded['goals'])) {
            $rawGoals = $decoded['goals'];
        }
        $normalized = [];
        if (is_array($rawGoals)) {
            foreach ($rawGoals as $goal) {
                $entry = normalize_goal_entry($goal);
                if ($entry['description'] === '') {
                    continue;
                }
                $normalized[] = $entry;
            }
        }
        $id = basename($file, '.json');
        $goals[$id] = $normalized;
    }
    return $goals;
}

/**
 * Save goal records for a single athlete.
 *
 * @param string $dir
 * @param string|int $athleteId
 * @param array<int,array<string,string>> $goals
 * @return bool
 */
function saveGoals(string $dir, $athleteId, array $goals): bool
{
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }
    }
    $normalized = [];
    foreach ($goals as $goal) {
        $entry = normalize_goal_entry($goal);
        if ($entry['description'] === '') {
            continue;
        }
        $normalized[] = $entry;
    }
    $json = json_encode($normalized, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    $path = rtrim($dir, '/\\') . '/' . $athleteId . '.json';
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

/**
 * Upsert a single athlete into an array of athletes.
 *
 * @param array<int, array<string,mixed>> $athletes
 * @param array<string,mixed>             $athlete
 * @return array<int, array<string,mixed>>
 */
function upsertAthlete(array $athletes, array $athlete): array
{
    if (empty($athlete['id'])) {
        return $athletes;
    }

    $normalized = [
        'id' => $athlete['id'],
        'firstname' => $athlete['firstname'] ?? '',
        'lastname' => $athlete['lastname'] ?? '',
        'username' => $athlete['username'] ?? '',
        'city' => $athlete['city'] ?? '',
        'country' => $athlete['country'] ?? '',
        'profile' => $athlete['profile'] ?? '',
    ];
    $found = false;
    foreach ($athletes as $idx => $existing) {
        if (($existing['id'] ?? null) == $normalized['id']) {
            $athletes[$idx] = $normalized;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $athletes[] = $normalized;
    }
    return $athletes;
}

/**
 * Handle Strava OAuth flow, token refresh, and data loading; routing is external.
 *
 * @param array<string,mixed> $query
 * @param array<string,mixed> $session
 * @param array<string,string> $config
 * @param array<string,mixed> $post
 * @return array<string,mixed>
 */
function buildStravaState(array $query, array $post, array &$session, array $config): array
{
    $action = $query['action'] ?? 'home';

    $clientId = $config['clientId'];
    $clientSecret = $config['clientSecret'];
    $redirectUri = $config['redirectUri'];
    $authBase = $config['authBase'] ?? 'https://www.strava.com/oauth/authorize';
    $tokenUrl = $config['tokenUrl'] ?? 'https://www.strava.com/api/v3/oauth/token';
    $scope = $config['scope'] ?? 'read,activity:read';
    $athletesDir = $config['athletesDir'] ?? null;
    $goalsDir = $config['goalsDir'] ?? null;
    $trainingsDir = $config['trainingsDir'] ?? null;
    $settingsDir = $config['settingsDir'] ?? null;
    $activitiesDir = $config['activitiesDir'] ?? null;
    $tokensDir = $config['tokensDir'] ?? null;
    $sessionLifetime = isset($config['sessionLifetime']) ? (int)$config['sessionLifetime'] : (int)ini_get('session.gc_maxlifetime');
    $secureCookies = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    $message = null;
    $grantedScope = $query['scope'] ?? null;
    $tokens = $session['strava_tokens'] ?? null;
    $rememberedAthleteId = null;
    if (isset($_COOKIE[SPARTA_STRAVA_REMEMBER_COOKIE]) && $_COOKIE[SPARTA_STRAVA_REMEMBER_COOKIE] !== '') {
        $rememberedAthleteId = (string)$_COOKIE[SPARTA_STRAVA_REMEMBER_COOKIE];
    }
    if (!$tokens && $tokensDir && $rememberedAthleteId) {
        $persisted = sparta_load_athlete_tokens($tokensDir, $rememberedAthleteId);
        if (!empty($persisted['tokens'])) {
            $tokens = $persisted['tokens'];
            $session['strava_tokens'] = $tokens;
            sparta_set_remembered_athlete($rememberedAthleteId, $sessionLifetime, $secureCookies);
        }
    }
    $athlete = null;
    $athleteError = null;
    $activities = null;
    $activitiesError = null;
    $activitiesUpdatedAt = null;
    $activitiesCacheFresh = false;
    $storageError = null;
    $goalMessage = null;
    $goalError = null;
    $saveGoalsSuccess = null;
    $nextTraining = null;
    $currentSettings = [];

    $athletes = $athletesDir ? loadAthletes($athletesDir) : [];
    $goalsMap = $goalsDir ? loadGoals($goalsDir) : [];
    $trainings = $trainingsDir ? load_trainings($trainingsDir) : [];
    if ($settingsDir && isset($session['strava_tokens']['athlete']['id'])) {
        $currentSettings = load_athlete_settings($settingsDir, $session['strava_tokens']['athlete']['id']);
    }

    if (!isset($session['oauth_state'])) {
        $session['oauth_state'] = bin2hex(random_bytes(16));
    }

    $logoutAthleteId = $tokens['athlete']['id'] ?? $rememberedAthleteId ?? null;
    if ($action === 'logout') {
        unset($session['strava_tokens']);
        if ($tokensDir && $logoutAthleteId) {
            sparta_delete_athlete_tokens($tokensDir, $logoutAthleteId);
        }
        sparta_set_remembered_athlete(null, $sessionLifetime, $secureCookies);
        $tokens = null;
        $message = 'Logged out of Strava session for this demo.';
    }

    if (isset($query['error'])) {
        $message = 'Authorization denied: ' . htmlspecialchars($query['error'], ENT_QUOTES, 'UTF-8');
    } elseif (isset($query['code']) && $action === 'home') {
        $callbackState = $query['state'] ?? '';
        if (!hash_equals($session['oauth_state'], $callbackState)) {
            $message = 'State mismatch. Please try again.';
        } elseif (!$clientSecret) {
            $message = 'Set STRAVA_CLIENT_SECRET in your environment to exchange the code for tokens.';
        } else {
            $result = requestTokens($tokenUrl, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $query['code'],
                'grant_type' => 'authorization_code',
            ]);
            if (isset($result['error'])) {
                $message = htmlspecialchars($result['error'], ENT_QUOTES, 'UTF-8');
            } else {
                $tokens = $result['tokens'];
                $session['strava_tokens'] = $tokens;
            }
        }
    }

    maybeRefreshTokens($tokens, $session, $message, $query, $clientId, $clientSecret, $tokenUrl);
    $persistentAthleteId = $tokens['athlete']['id'] ?? $rememberedAthleteId ?? null;
    if ($tokensDir && $tokens && $persistentAthleteId) {
        sparta_save_athlete_tokens($tokensDir, $persistentAthleteId, $tokens);
        sparta_set_remembered_athlete($persistentAthleteId, $sessionLifetime, $secureCookies);
    }

    if ($tokens && !empty($tokens['access_token'])) {
        if (!empty($tokens['athlete'])) {
            $athlete = $tokens['athlete'];
        } else {
            $athleteResult = fetchAthlete($tokens['access_token']);
            if (isset($athleteResult['athlete'])) {
                $athlete = $athleteResult['athlete'];
            } elseif (isset($athleteResult['error'])) {
                $athleteError = htmlspecialchars($athleteResult['error'], ENT_QUOTES, 'UTF-8');
            }
        }

        if ($activitiesDir && !empty($athlete['id'])) {
            $cached = sparta_load_athlete_activities($activitiesDir, $athlete['id']);
            if (!empty($cached['activities']) && !empty($cached['updated_at'])) {
                $updatedAt = strtotime((string)$cached['updated_at']);
                if ($updatedAt !== false && (time() - $updatedAt) < 600) {
                    $activities = $cached['activities'];
                    $activitiesUpdatedAt = $cached['updated_at'];
                    $activitiesCacheFresh = true;
                }
            }
        }

        if (!$activitiesCacheFresh) {
            $activitiesResult = fetchActivities($tokens['access_token']);
            if (isset($activitiesResult['activities'])) {
                $activities = sparta_filter_activities($activitiesResult['activities']);
                if ($activities) {
                    $lapsFetched = 0;
                    foreach ($activities as $idx => $activity) {
                        if ($lapsFetched >= 10) {
                            break;
                        }
                        $activityId = $activity['id'] ?? null;
                        if (!$activityId) {
                            continue;
                        }
                        $lapsResult = fetchActivityLaps($tokens['access_token'], $activityId);
                        if (isset($lapsResult['laps'])) {
                            $activities[$idx]['laps'] = $lapsResult['laps'];
                        }
                        $lapsFetched++;
                    }
                }
                $activitiesUpdatedAt = date('c');
                if ($activitiesDir && !empty($athlete['id'])) {
                    sparta_save_athlete_activities($activitiesDir, $athlete['id'], $activities);
                }
            } elseif (isset($activitiesResult['error'])) {
                $activitiesError = htmlspecialchars($activitiesResult['error'], ENT_QUOTES, 'UTF-8');
                if ($activitiesDir && !empty($athlete['id'])) {
                    $cached = sparta_load_athlete_activities($activitiesDir, $athlete['id']);
                    if (!empty($cached['activities'])) {
                        $activities = $cached['activities'];
                        $activitiesUpdatedAt = $cached['updated_at'] ?? null;
                    }
                }
            }
        }
    }

    // Handle goal updates for current athlete
    if ($action === 'goals' && $athlete && !empty($athlete['id']) && $goalsDir) {
        $athleteId = $athlete['id'];
        $currentGoals = $goalsMap[$athleteId] ?? [];
        $didUpdateGoals = false;

        if (!empty($post['delete_goal']) && !empty($post['goal_id'])) {
            $goalId = (string)$post['goal_id'];
            $filtered = array_values(array_filter($currentGoals, static function ($goal) use ($goalId) {
                return ($goal['id'] ?? null) !== $goalId;
            }));
            if (count($filtered) === count($currentGoals)) {
                $goalError = 'Goal niet gevonden om te verwijderen.';
            } else {
                $currentGoals = $filtered;
                $goalMessage = 'Goal verwijderd.';
                $didUpdateGoals = true;
            }
        } elseif (!empty($post['update_goal']) && !empty($post['goal_id'])) {
            $goalId = (string)$post['goal_id'];
            $description = trim((string)($post['goal_description'] ?? ''));
            $targetDate = normalize_goal_date((string)($post['goal_target_date'] ?? ''));
            if ($description === '' || $targetDate === '') {
                $goalError = 'Vul een omschrijving en geldige datum (YYYY-MM-DD) in.';
            } else {
                foreach ($currentGoals as &$goalItem) {
                    if (($goalItem['id'] ?? null) !== $goalId) {
                        continue;
                    }
                    $goalItem['description'] = $description;
                    $goalItem['target_date'] = $targetDate;
                    $goalItem['updated_at'] = date('c');
                    $goalMessage = 'Goal bijgewerkt.';
                    $didUpdateGoals = true;
                    break;
                }
                unset($goalItem);
                if (!$didUpdateGoals && !$goalError) {
                    $goalError = 'Goal niet gevonden om te wijzigen.';
                }
            }
        } elseif (!empty($post['add_goal'])) {
            $description = trim((string)($post['goal_description'] ?? ''));
            $targetDate = normalize_goal_date((string)($post['goal_target_date'] ?? ''));
            if ($description === '' || $targetDate === '') {
                $goalError = 'Vul een omschrijving en geldige datum (YYYY-MM-DD) in.';
            } else {
                $currentGoals[] = normalize_goal_entry([
                    'description' => $description,
                    'target_date' => $targetDate,
                ]);
                $goalMessage = 'Goal toegevoegd.';
                $didUpdateGoals = true;
            }
        }

        if ($didUpdateGoals && !$goalError) {
            if (!saveGoals($goalsDir, $athleteId, $currentGoals)) {
                $storageError = 'Unable to save goals.';
            } else {
                $saveGoalsSuccess = $goalMessage;
                $athlete['goals'] = $currentGoals;
                $goalsMap[$athleteId] = $currentGoals;
            }
        }
    }

    if ($athlete && !empty($athlete['id']) && $athletesDir) {
        $updated = upsertAthlete($athletes, $athlete);
        if ($updated !== $athletes) {
            $athletes = $updated;
            if (!saveAthleteRecord($athletesDir, $athlete)) {
                $storageError = 'Unable to save athlete data.';
            }
        }
    }

    $loginUrl = $authBase . '?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'approval_prompt' => 'auto',
        'scope' => $scope,
        'state' => $session['oauth_state'],
    ]);

    // Attach goals from storage
    if ($goalsMap) {
        if ($athlete && !empty($athlete['id']) && isset($goalsMap[$athlete['id']])) {
            $athlete['goals'] = $goalsMap[$athlete['id']];
        }
        foreach ($athletes as &$storedAthlete) {
            $aid = $storedAthlete['id'] ?? null;
            if ($aid !== null && isset($goalsMap[$aid])) {
                $storedAthlete['goals'] = $goalsMap[$aid];
            }
        }
        unset($storedAthlete);
    }

    $currentAthleteName = $athlete ? trim(($athlete['firstname'] ?? '') . ' ' . ($athlete['lastname'] ?? '')) : null;
    $currentAthletePhoto = $athlete ? ($athlete['profile'] ?? null) : null;
    $isAdmin = user_is_admin($athlete['id'] ?? null);

    if ($settingsDir && $athletes) {
        $paceTable = get_pace_table();
        foreach ($athletes as &$athleteRow) {
            if (empty($athleteRow['id'])) {
                continue;
            }
            $settings = load_athlete_settings($settingsDir, $athleteRow['id']);
            $paceRow = pace_row_from_settings($settings, $paceTable);
            if (!empty($paceRow['ten_k'])) {
                $athleteRow['pace_10k'] = (string)$paceRow['ten_k'];
            }
        }
        unset($athleteRow);
    }

    if ($trainingsDir && !empty($athlete['id'])) {
        $trainings = load_trainings($trainingsDir, $athlete['id']);
        if (!$trainings) {
            $trainings = load_trainings($trainingsDir);
        }
    }

    if ($trainings) {
        $today = new DateTimeImmutable('today');
        $upcoming = [];
        foreach ($trainings as $trainingDay) {
            $date = $trainingDay['date'] ?? null;
            if (!$date) {
                continue;
            }
            try {
                $dateObj = new DateTimeImmutable($date);
            } catch (Exception $e) {
                continue;
            }
            if ($dateObj < $today) {
                continue;
            }
            $upcoming[] = [
                'date' => $dateObj->format('Y-m-d'),
                'entries' => $trainingDay['entries'] ?? [],
            ];
        }
        usort($upcoming, static function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        $nextTraining = $upcoming[0] ?? null;
    }

    $data = [
        'loginUrl' => $loginUrl,
        'redirectUri' => $redirectUri,
        'clientId' => $clientId,
        'scope' => $scope,
        'grantedScope' => $grantedScope,
        'message' => $message,
        'tokens' => $tokens,
        'athlete' => $athlete,
        'athleteError' => $athleteError,
        'activities' => $activities,
        'activitiesError' => $activitiesError,
        'activitiesUpdatedAt' => $activitiesUpdatedAt,
        'currentAthleteName' => $currentAthleteName,
        'currentAction' => $action,
        'currentAthleteId' => $athlete['id'] ?? null,
        'currentAthletePhoto' => $currentAthletePhoto,
        'isAdmin' => $isAdmin,
        'athletes' => $athletes,
        'storageError' => $storageError,
        'saveGoalsSuccess' => $saveGoalsSuccess,
        'goalMessage' => $goalMessage,
        'goalError' => $goalError,
        'nextTraining' => $nextTraining,
        'trainings' => $trainings,
        'athleteSettings' => $currentSettings,
        'athlete' => $athlete,
    ];

    $data['otherAthletes'] = array_values($athletes);
    return $data;
}

/**
 * Refresh tokens when close to expiry.
 *
 * @param array<string,mixed>|null $tokens
 * @param array<string,mixed>      $session
 * @param string|null              $message
 * @param array<string,mixed>      $query
 * @param string                   $clientId
 * @param string                   $clientSecret
 * @param string                   $tokenUrl
 */
function maybeRefreshTokens(?array &$tokens, array &$session, ?string &$message, array $query, string $clientId, string $clientSecret, string $tokenUrl): void
{
    if (!$tokens || !isset($tokens['expires_at'])) {
        return;
    }
    $now = time();
    if ($tokens['expires_at'] > $now + 60) {
        return;
    }
    if (empty($tokens['refresh_token']) || !$clientSecret) {
        return;
    }
    $existingAthlete = $session['strava_tokens']['athlete'] ?? null;

    $refresh = requestTokens($tokenUrl, [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
    ]);
    if (isset($refresh['tokens'])) {
        $tokens = $refresh['tokens'];
        if (!isset($tokens['athlete']) && $existingAthlete !== null) {
            $tokens['athlete'] = $existingAthlete;
        }
        $session['strava_tokens'] = $tokens;
        $message = 'Access token refreshed.';
    } elseif (!isset($query['error']) && !isset($query['code'])) {
        $message = htmlspecialchars($refresh['error'] ?? $message, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Build the chart data needed to render the per-activity tempo graph.
 *
 * @param array<string,mixed> $activity
 * @param array<string,string> $tempoPaces
 * @param string $chartId
 * @return array<string,mixed>|null
 */
function sparta_build_activity_chart_data(array $activity, array $tempoPaces, string $chartId): ?array
{
    $laps = (isset($activity['laps']) && is_array($activity['laps'])) ? $activity['laps'] : [];
    if ($laps === []) {
        return null;
    }

    $opacityMap = [
        '3K' => 1.0,
        '5K' => 0.8,
        '10K' => 0.6,
        'Half Marathon' => 0.4,
        'Marathon' => 0.2,
        'Aeroob' => 0.1,
        'Recovery' => 0.1,
        'Onbekend' => 0.5,
    ];
    $defaultZoneOrder = ['3K', '5K', '10K', 'Half Marathon', 'Marathon', 'Aeroob', 'Recovery'];
    $zoneLabels = array_filter(array_keys($tempoPaces), static fn($label) => $label !== 'Aerobe');
    if ($zoneLabels === []) {
        $zoneLabels = $defaultZoneOrder;
    } else {
        $zoneLabels = array_values(array_unique(array_merge($zoneLabels, $defaultZoneOrder)));
    }

    $zoneCount = count($zoneLabels);
    $zoneIndexMap = array_flip($zoneLabels);
    $bars = [];

    foreach ($laps as $lapIndex => $lap) {
        $lapName = trim((string)($lap['name'] ?? ''));
        $lapDistance = null;
        if (isset($lap['distance']) && is_numeric($lap['distance'])) {
            $lapDistance = (float)$lap['distance'] / 1000;
        }
        $lapSeconds = null;
        if (isset($lap['elapsed_time']) && is_numeric($lap['elapsed_time'])) {
            $lapSeconds = (int)$lap['elapsed_time'];
        } elseif (isset($lap['moving_time']) && is_numeric($lap['moving_time'])) {
            $lapSeconds = (int)$lap['moving_time'];
        }
        if ($lapDistance === null || $lapSeconds === null || $lapDistance <= 0 || $lapSeconds <= 0) {
            continue;
        }
        $lapPaceSeconds = (int)round($lapSeconds / $lapDistance);
        $tempoZone = sparta_find_tempo_zone($tempoPaces, $lapPaceSeconds);
        $lapLabel = $lapName !== '' ? $lapName : (string)($lapIndex + 1);
        $bars[] = [
            'label' => $lapLabel,
            'distance' => $lapDistance,
            'pace' => format_pace_seconds($lapPaceSeconds),
            'duration' => sparta_format_duration($lapSeconds),
            'zone' => $tempoZone,
            'zone_index' => $tempoZone !== null && isset($zoneIndexMap[$tempoZone]) ? $zoneIndexMap[$tempoZone] : null,
            'opacity' => $tempoZone && isset($opacityMap[$tempoZone]) ? $opacityMap[$tempoZone] : 0.7,
        ];
    }

    if ($bars === []) {
        return null;
    }

    $hasUnknownZone = false;
    foreach ($bars as $bar) {
        if ($bar['zone_index'] === null) {
            $hasUnknownZone = true;
            break;
        }
    }
    if ($hasUnknownZone && !in_array('Onbekend', $zoneLabels, true)) {
        $zoneLabels[] = 'Onbekend';
    }

    $zoneCount = count($zoneLabels);
    $zoneIndexMap = array_flip($zoneLabels);
    foreach ($bars as $barIndex => $bar) {
        if ($bar['zone_index'] === null) {
            $bars[$barIndex]['zone_index'] = $zoneIndexMap['Onbekend'] ?? ($zoneCount - 1);
            $bars[$barIndex]['zone'] = $bar['zone'] ?: 'Onbekend';
        }
    }

    $zoneStackOrder = array_reverse($zoneLabels);
    $stackValues = [];
    foreach ($zoneStackOrder as $zoneLabel) {
        $stackValues[$zoneLabel] = [];
    }
    foreach ($bars as $bar) {
        $zoneIndex = $bar['zone_index'] ?? ($zoneCount - 1);
        $slowIndex = ($zoneCount - 1) - $zoneIndex;
        foreach ($zoneStackOrder as $stackIndex => $zoneLabel) {
            $stackValues[$zoneLabel][] = $stackIndex <= $slowIndex ? 1 : 0;
        }
    }

    $lapRanges = [];
    $lapMidpoints = [];
    $zoneLevels = [];
    $distanceCursor = 0.0;
    foreach ($bars as $bar) {
        $start = $distanceCursor;
        $end = $distanceCursor + $bar['distance'];
        $distanceCursor = $end;
        $lapRanges[] = [$start, $end];
        $lapMidpoints[] = ($start + $end) / 2;
        $zoneIndex = $bar['zone_index'] ?? ($zoneCount - 1);
        $slowIndex = ($zoneCount - 1) - $zoneIndex;
        $zoneLevels[] = $slowIndex + 1;
    }

    return [
        'id' => $chartId,
        'labels' => array_values(array_map(static fn($bar) => $bar['label'], $bars)),
        'zoneLabels' => array_values($zoneLabels),
        'zoneStackOrder' => array_values($zoneStackOrder),
        'zoneStacks' => $stackValues,
        'lapRanges' => $lapRanges,
        'lapMidpoints' => $lapMidpoints,
        'zoneLevels' => $zoneLevels,
        'totalDistance' => $distanceCursor,
        'opacities' => array_values(array_map(static fn($bar) => $bar['opacity'], $bars)),
        'distances' => array_values(array_map(static fn($bar) => $bar['distance'], $bars)),
        'durations' => array_values(array_map(static fn($bar) => $bar['duration'], $bars)),
        'paces' => array_values(array_map(static fn($bar) => $bar['pace'], $bars)),
        'zones' => array_values(array_map(static fn($bar) => $bar['zone'], $bars)),
    ];
}
