<?php

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
function fetchActivities(string $accessToken, int $perPage = 5): array
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

    $message = null;
    $grantedScope = $query['scope'] ?? null;
    $tokens = $session['strava_tokens'] ?? null;
    $athlete = null;
    $athleteError = null;
    $activities = null;
    $activitiesError = null;
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

    if ($action === 'logout') {
        unset($session['strava_tokens']);
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

        $activitiesResult = fetchActivities($tokens['access_token']);
        if (isset($activitiesResult['activities'])) {
            $activities = $activitiesResult['activities'];
        } elseif (isset($activitiesResult['error'])) {
            $activitiesError = htmlspecialchars($activitiesResult['error'], ENT_QUOTES, 'UTF-8');
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
            $baseSeconds = null;
            if (isset($settings['base_pace_seconds'])) {
                $baseSeconds = (int)$settings['base_pace_seconds'];
            } elseif (!empty($settings['base_pace_input'])) {
                $baseSeconds = parse_pace_to_seconds((string)$settings['base_pace_input']);
            }
            if ($baseSeconds !== null) {
                $paceRow = find_pace_row($paceTable, $baseSeconds);
                if (!empty($paceRow['ten_k'])) {
                    $athleteRow['pace_10k'] = (string)$paceRow['ten_k'];
                }
            }
        }
        unset($athleteRow);
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

    $refresh = requestTokens($tokenUrl, [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
    ]);
    if (isset($refresh['tokens'])) {
        $tokens = $refresh['tokens'];
        $session['strava_tokens'] = $tokens;
        $message = 'Access token refreshed.';
    } elseif (!isset($query['error']) && !isset($query['code'])) {
        $message = htmlspecialchars($refresh['error'] ?? $message, ENT_QUOTES, 'UTF-8');
    }
}
