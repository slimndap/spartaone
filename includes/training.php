<?php

/**
 * Canonical race options for scheduled trainings.
 *
 * @return array<string,string>
 */
function sparta_training_race_options(): array
{
    return [
        '5k' => '5K wedstrijd',
        '10k' => '10K wedstrijd',
        '10m' => '10M wedstrijd',
        'half-marathon' => 'Halve Marathon wedstrijd',
        'marathon' => 'Marathon wedstrijd',
    ];
}

/**
 * Normalize stored race data to a stable key.
 */
function sparta_normalize_training_race_type($raceType): string
{
    $allowed = sparta_training_race_options();
    if (is_string($raceType)) {
        $raceType = trim($raceType);
        if (isset($allowed[$raceType])) {
            return $raceType;
        }
    }
    return '';
}

/**
 * Normalize athlete finish times keyed by athlete id.
 *
 * @param mixed $finishTimes
 * @return array<string,string>
 */
function sparta_normalize_training_finish_times($finishTimes): array
{
    if (!is_array($finishTimes)) {
        return [];
    }
    $normalized = [];
    foreach ($finishTimes as $athleteId => $time) {
        $athleteId = trim((string)$athleteId);
        $time = trim((string)$time);
        if ($athleteId === '' || $time === '') {
            continue;
        }
        $normalized[$athleteId] = $time;
    }
    return $normalized;
}

/**
 * Build a display name for an athlete record.
 *
 * @param array<string,mixed> $athlete
 */
function sparta_training_athlete_name(array $athlete): string
{
    $fullName = trim((string)($athlete['firstname'] ?? '') . ' ' . (string)($athlete['lastname'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }
    $username = trim((string)($athlete['username'] ?? ''));
    if ($username !== '') {
        return $username;
    }
    return 'Onbekende sporter';
}

/**
 * Convert a stored gender key to a user-facing label.
 */
function sparta_training_gender_label($gender): string
{
    $gender = trim((string)$gender);
    $labels = [
        'male' => 'Man',
        'female' => 'Vrouw',
        'non_binary' => 'Non-binair',
        'other' => 'Anders',
        'prefer_not_to_say' => 'Niet opgegeven',
    ];
    return $labels[$gender] ?? '';
}

/**
 * Calculate the current age from a YYYY-MM-DD birthdate.
 */
function sparta_training_age_from_birthdate($birthdate): ?int
{
    return sparta_training_age_from_birthdate_on_date($birthdate, (new DateTimeImmutable('today'))->format('Y-m-d'));
}

/**
 * Call OpenAI Chat API to convert a CSV schedule into structured trainings per date.
 *
 * @param string $csvText Raw CSV text pasted by the user.
 * @return array<string,mixed> ['trainings' => array] on success, or ['error' => string]
 */
function parse_training_csv_with_openai(string $csvText): array
{
    $apiKey = function_exists('sparta_env') ? sparta_env('OPENAI_API_KEY') : (getenv('OPENAI_API_KEY') ?: '');
    if (!$apiKey) {
        return ['error' => 'OPENAI_API_KEY is not configured on the server.'];
    }

    $allowedTempos = ['3K', '5K', '10K', 'Half Marathon', 'Marathon', 'Aeroob', 'Recovery'];

    $payload = [
        'model' => 'gpt-5.2',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You convert training schedules from CSV into JSON grouped by date.',
            ],
            [
                'role' => 'user',
                'content' => implode("\n", [
                    'Input is CSV text with columns like Date, Activity, Distance, Notes.',
                    'Return strictly JSON with this shape: {"trainings":[{"date":"YYYY-MM-DD","entries":[{"title":"string","activity":"string","distance":"string","notes":"string","tempos":["3K","5K","10K","Half Marathon","Marathon","Aeroob","Recovery"]}]}]}',
                    'Parse each row; put any extra columns into notes. Normalize date to YYYY-MM-DD.',
                    'Fill "tempos" with zero or more of the allowed options based on the activity description; use an empty array if none apply.',
                    'Notes must be empty string unless the CSV explicitly has notes content for that row.',
                    'Do not include any other text.',
                    'CSV:',
                    $csvText,
                ]),
            ],
        ],
        'temperature' => 0,
        'response_format' => ['type' => 'json_object'],
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 120,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        return ['error' => 'OpenAI request failed: ' . $curlErr];
    }
    if ($httpCode >= 400) {
        return ['error' => 'OpenAI request failed with status ' . $httpCode . ': ' . $response];
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'OpenAI response was not valid JSON.'];
    }

    $content = $decoded['choices'][0]['message']['content'] ?? null;
    if (!$content) {
        return ['error' => 'OpenAI response missing content.'];
    }

    $asJson = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($asJson['trainings'])) {
        return ['error' => 'OpenAI content was not in the expected format.'];
    }

    $trainings = $asJson['trainings'];
    if (!is_array($trainings)) {
        return ['error' => 'Trainings payload was not an array.'];
    }

    $normalizeTempos = static function ($value) use ($allowedTempos): array {
        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }
        if (!is_array($value)) {
            return [];
        }
        $filtered = [];
        foreach ($value as $item) {
            $item = (string)$item;
            if ($item === '') {
                continue;
            }
            // Normalize legacy label.
            if ($item === 'Aerobe') {
                $item = 'Aeroob';
            }
            if (in_array($item, $allowedTempos, true) && !in_array($item, $filtered, true)) {
                $filtered[] = $item;
            }
        }
        return $filtered;
    };

    foreach ($trainings as &$training) {
        if (empty($training['entries']) || !is_array($training['entries'])) {
            $training['entries'] = [];
        }
        foreach ($training['entries'] as &$entry) {
            if (!isset($entry['title'])) {
                $entry['title'] = '';
            }
            if (!isset($entry['notes']) || $entry['notes'] === null) {
                $entry['notes'] = '';
            }
            $entry['race_type'] = sparta_normalize_training_race_type($entry['race_type'] ?? '');
            $entry['finish_times'] = sparta_normalize_training_finish_times($entry['finish_times'] ?? []);
            $entry['tempos'] = $normalizeTempos($entry['tempos'] ?? []);
        }
        unset($entry);
    }
    unset($training);

    return ['trainings' => $trainings];
}

/**
 * Persist trainings to JSON (shared for all athletes).
 *
 * @param string $dir Base directory to store training data.
 * @param array<int,array<string,mixed>> $trainings Parsed trainings from OpenAI.
 */
function save_trainings(string $dir, array $trainings, $scope = 'shared'): bool
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    $id = $scope ?? 'shared';
    $path = rtrim($dir, '/\\') . '/' . $id . '.json';
    $payload = [
        'scope' => (string)$id,
        'trainings' => $trainings,
        'updated_at' => date('c'),
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    $tempPath = tempnam($dir, '.sparta-trainings-');
    if ($tempPath === false) {
        return false;
    }
    if (file_put_contents($tempPath, $json, LOCK_EX) === false) {
        @unlink($tempPath);
        return false;
    }
    if (is_file($path)) {
        $permissions = @fileperms($path);
        if ($permissions !== false) {
            @chmod($tempPath, $permissions & 0777);
        }
    }
    if (!rename($tempPath, $path)) {
        @unlink($tempPath);
        return false;
    }
    return true;
}

/**
 * Make a timestamped recovery copy before changing a stored schedule.
 *
 * @return string|false Backup path, or false when no safe backup could be made.
 */
function sparta_backup_trainings(string $dir, $scope = 'shared')
{
    $id = $scope ?? 'shared';
    $path = rtrim($dir, '/\\') . '/' . $id . '.json';
    if (!is_readable($path)) {
        return false;
    }
    $backupDir = rtrim($dir, '/\\') . '/backups';
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        return false;
    }
    try {
        $suffix = bin2hex(random_bytes(3));
    } catch (Exception $e) {
        $suffix = str_replace('.', '', uniqid('', true));
    }
    $backupPath = $backupDir . '/' . $id . '-' . date('Ymd-His') . '-' . $suffix . '.json';
    $contents = file_get_contents($path);
    if ($contents === false || file_put_contents($backupPath, $contents, LOCK_EX) === false) {
        return false;
    }
    return $backupPath;
}

/**
 * Merge imported trainings into the existing schedule without removing older data.
 *
 * Existing days are preserved and new entries are appended only when they are not exact duplicates.
 *
 * @param array<int,array<string,mixed>> $existingTrainings
 * @param array<int,array<string,mixed>> $importedTrainings
 * @return array{trainings: array<int,array<string,mixed>>, added_days: int, added_entries: int, skipped_duplicates: int}
 */
function sparta_merge_trainings_with_summary(array $existingTrainings, array $importedTrainings): array
{
    $mergedByDate = [];
    $addedDays = 0;
    $addedEntries = 0;
    $skippedDuplicates = 0;

    $normalizeDay = static function (array $trainingDay): ?array {
        $date = trim((string)($trainingDay['date'] ?? ''));
        $entries = $trainingDay['entries'] ?? [];
        if ($date === '' || !is_array($entries)) {
            return null;
        }

        $normalizedEntries = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $normalizedEntry = [
                'title' => (string)($entry['title'] ?? ''),
                'activity' => (string)($entry['activity'] ?? ''),
                'distance' => (string)($entry['distance'] ?? ''),
                'notes' => (string)($entry['notes'] ?? ''),
                'race_type' => sparta_normalize_training_race_type($entry['race_type'] ?? ''),
                'finish_times' => sparta_normalize_training_finish_times($entry['finish_times'] ?? []),
                'tempos' => is_array($entry['tempos'] ?? null) ? array_values($entry['tempos']) : [],
            ];
            foreach (['source_id', 'source_key'] as $sourceField) {
                $sourceValue = trim((string)($entry[$sourceField] ?? ''));
                if ($sourceValue !== '') {
                    $normalizedEntry[$sourceField] = $sourceValue;
                }
            }
            $normalizedEntries[] = $normalizedEntry;
        }

        return [
            'date' => $date,
            'entries' => $normalizedEntries,
        ];
    };

    foreach ($existingTrainings as $trainingDay) {
        $normalized = is_array($trainingDay) ? $normalizeDay($trainingDay) : null;
        if ($normalized === null) {
            continue;
        }
        $mergedByDate[$normalized['date']] = $normalized;
    }

    foreach ($importedTrainings as $trainingDay) {
        $normalized = is_array($trainingDay) ? $normalizeDay($trainingDay) : null;
        if ($normalized === null) {
            continue;
        }
        $date = $normalized['date'];
        if (!isset($mergedByDate[$date])) {
            $mergedByDate[$date] = $normalized;
            $addedDays++;
            $addedEntries += count($normalized['entries']);
            continue;
        }

        $existingEntries = $mergedByDate[$date]['entries'];
        $existingFingerprints = [];
        foreach ($existingEntries as $entry) {
            $fingerprint = json_encode($entry);
            if ($fingerprint !== false) {
                $existingFingerprints[$fingerprint] = true;
            }
        }

        foreach ($normalized['entries'] as $entry) {
            $fingerprint = json_encode($entry);
            if ($fingerprint === false) {
                continue;
            }
            if (isset($existingFingerprints[$fingerprint])) {
                $skippedDuplicates++;
                continue;
            }
            $mergedByDate[$date]['entries'][] = $entry;
            $existingFingerprints[$fingerprint] = true;
            $addedEntries++;
        }
    }

    ksort($mergedByDate);

    return [
        'trainings' => array_values($mergedByDate),
        'added_days' => $addedDays,
        'added_entries' => $addedEntries,
        'skipped_duplicates' => $skippedDuplicates,
    ];
}

/**
 * Backward-compatible training merge helper that returns only the merged schedule.
 *
 * @param array<int,array<string,mixed>> $existingTrainings
 * @param array<int,array<string,mixed>> $importedTrainings
 * @return array<int,array<string,mixed>>
 */
function sparta_merge_trainings(array $existingTrainings, array $importedTrainings): array
{
    $result = sparta_merge_trainings_with_summary($existingTrainings, $importedTrainings);
    return $result['trainings'];
}

function sparta_lars_den_haag_source_id(): string
{
    return 'lars-den-haag-2026';
}

function sparta_lars_den_haag_source_path(): string
{
    return __DIR__ . '/../resources/training/lars-den-haag-2026.json';
}

/**
 * Map a Lars zone to the tempo labels understood by SpartaOne.
 *
 * @return array<int,string>
 */
function sparta_lars_den_haag_tempos(string $zone): array
{
    $zone = strtolower($zone);
    $tempos = [];
    $add = static function (string $tempo) use (&$tempos): void {
        if (!in_array($tempo, $tempos, true)) {
            $tempos[] = $tempo;
        }
    };
    if (strpos($zone, '5k') !== false) {
        $add('5K');
    }
    if (strpos($zone, '10k') !== false) {
        $add('10K');
    }
    if (strpos($zone, 'hm') !== false || strpos($zone, 'halve') !== false || strpos($zone, 'drempel') !== false) {
        $add('Half Marathon');
    }
    if (strpos($zone, 'marathon') !== false || strpos($zone, 'm-tempo') !== false) {
        $add('Marathon');
    }
    if (strpos($zone, 'aeroob') !== false || strpos($zone, 'moderate') !== false) {
        $add('Aeroob');
    }
    if (strpos($zone, 'herstel') !== false) {
        $add('Recovery');
    }
    return $tempos;
}

/**
 * Convert and validate the reusable Lars source payload.
 *
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function sparta_convert_lars_den_haag_source(array $payload): array
{
    $sourceId = sparta_lars_den_haag_source_id();
    if (($payload['source_id'] ?? '') !== $sourceId) {
        return ['error' => 'De Lars-bron heeft een onverwachte source_id.'];
    }
    $sessions = $payload['sessions'] ?? null;
    if (!is_array($sessions) || count($sessions) !== 123) {
        return ['error' => 'De Lars-bron moet exact 123 sessies bevatten.'];
    }

    $trainings = [];
    $seenKeys = [];
    foreach ($sessions as $session) {
        if (!is_array($session)) {
            return ['error' => 'De Lars-bron bevat een ongeldige sessie.'];
        }
        $date = trim((string)($session['date'] ?? ''));
        $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateValue || $dateValue->format('Y-m-d') !== $date) {
            return ['error' => 'De Lars-bron bevat een ongeldige datum.'];
        }
        $sourceKey = $sourceId . ':' . $date;
        if (isset($seenKeys[$sourceKey])) {
            return ['error' => 'De Lars-bron bevat een dubbele source_key: ' . $sourceKey . '.'];
        }
        $seenKeys[$sourceKey] = true;

        $description = trim((string)($session['description'] ?? ''));
        $description = preg_replace('/\s*\|\s*EKIDEN(?:-LOPERS)?\b.*$/iu', '', $description);
        $description = trim((string)$description);
        if ($description === '') {
            return ['error' => 'De Lars-bron bevat een sessie zonder omschrijving op ' . $date . '.'];
        }

        $activity = trim((string)($session['type'] ?? ''));
        $activity = str_replace([' / Kracht', 'Kracht / '], '', $activity);
        $activity = trim($activity);

        $notes = str_replace('Krachttraining.', '', (string)($session['notes'] ?? ''));
        $notes = trim((string)preg_replace('/\s+/u', ' ', $notes));
        if (empty($session['active']) && $date !== '2026-11-01' && stripos($notes, 'optioneel') !== 0) {
            $notes = trim('Optioneel. ' . $notes);
        }

        $duration = trim((string)($session['duration'] ?? ''));
        $distance = ($duration === '' || $duration === '—') ? '' : 'Duur ' . $duration;
        $raceType = '';
        if ($date === '2026-09-20') {
            $raceType = 'half-marathon';
        } elseif ($date === '2026-11-01') {
            $raceType = 'marathon';
        }
        $tempos = sparta_lars_den_haag_tempos((string)($session['zone'] ?? ''));
        if ($raceType === 'half-marathon' && !in_array('Half Marathon', $tempos, true)) {
            $tempos[] = 'Half Marathon';
        }
        if ($raceType === 'marathon' && !in_array('Marathon', $tempos, true)) {
            $tempos[] = 'Marathon';
        }

        $trainings[] = [
            'date' => $date,
            'entries' => [[
                'title' => 'Den Haag — ' . $description,
                'activity' => $activity,
                'distance' => $distance,
                'notes' => $notes,
                'race_type' => $raceType,
                'finish_times' => [],
                'tempos' => $tempos,
                'source_id' => $sourceId,
                'source_key' => $sourceKey,
            ]],
        ];
    }

    usort($trainings, static fn($left, $right) => strcmp((string)$left['date'], (string)$right['date']));
    if (($trainings[0]['date'] ?? '') !== '2026-06-29' || ($trainings[count($trainings) - 1]['date'] ?? '') !== '2026-11-01') {
        return ['error' => 'Het datumbereik van de Lars-bron is niet 29 juni t/m 1 november 2026.'];
    }

    return [
        'error' => '',
        'source_id' => $sourceId,
        'source_count' => count($trainings),
        'start_date' => $trainings[0]['date'],
        'end_date' => $trainings[count($trainings) - 1]['date'],
        'trainings' => $trainings,
    ];
}

/**
 * Load the reusable source artifact and prepare it for an import preview or write.
 *
 * @return array<string,mixed>
 */
function sparta_prepare_lars_den_haag_import(?string $path = null): array
{
    $path = $path ?: sparta_lars_den_haag_source_path();
    if (!is_readable($path)) {
        return ['error' => 'De Lars-importbron kan niet worden gelezen.'];
    }
    $contents = file_get_contents($path);
    $payload = $contents === false ? null : json_decode($contents, true);
    if (!is_array($payload)) {
        return ['error' => 'De Lars-importbron bevat geen geldige JSON.'];
    }
    return sparta_convert_lars_den_haag_source($payload);
}

/**
 * Remove only entries owned by one import source.
 *
 * @param array<int,array<string,mixed>> $trainings
 * @return array{trainings: array<int,array<string,mixed>>, removed_entries: int, removed_days: int}
 */
function sparta_remove_training_source(array $trainings, string $sourceId): array
{
    $remaining = [];
    $removedEntries = 0;
    $removedDays = 0;
    foreach ($trainings as $trainingDay) {
        if (!is_array($trainingDay)) {
            continue;
        }
        $entries = is_array($trainingDay['entries'] ?? null) ? $trainingDay['entries'] : [];
        $keptEntries = [];
        foreach ($entries as $entry) {
            if (is_array($entry) && (string)($entry['source_id'] ?? '') === $sourceId) {
                $removedEntries++;
                continue;
            }
            $keptEntries[] = $entry;
        }
        if ($keptEntries === []) {
            if ($entries !== []) {
                $removedDays++;
            }
            continue;
        }
        $trainingDay['entries'] = array_values($keptEntries);
        $remaining[] = $trainingDay;
    }
    usort($remaining, static fn($left, $right) => strcmp((string)($left['date'] ?? ''), (string)($right['date'] ?? '')));
    return [
        'trainings' => $remaining,
        'removed_entries' => $removedEntries,
        'removed_days' => $removedDays,
    ];
}

/**
 * Replace one source atomically in memory while preserving every unrelated entry.
 *
 * @param array<int,array<string,mixed>> $existingTrainings
 * @param array<int,array<string,mixed>> $importedTrainings
 * @return array<string,mixed>
 */
function sparta_replace_training_source(array $existingTrainings, array $importedTrainings, string $sourceId): array
{
    $removed = sparta_remove_training_source($existingTrainings, $sourceId);
    $byDate = [];
    foreach ($removed['trainings'] as $trainingDay) {
        $date = trim((string)($trainingDay['date'] ?? ''));
        if ($date !== '') {
            $byDate[$date] = $trainingDay;
        }
    }
    $addedEntries = 0;
    $addedDays = 0;
    foreach ($importedTrainings as $trainingDay) {
        $date = trim((string)($trainingDay['date'] ?? ''));
        $entries = is_array($trainingDay['entries'] ?? null) ? $trainingDay['entries'] : [];
        if ($date === '' || $entries === []) {
            continue;
        }
        if (!isset($byDate[$date])) {
            $byDate[$date] = ['date' => $date, 'entries' => []];
            $addedDays++;
        }
        foreach ($entries as $entry) {
            $byDate[$date]['entries'][] = $entry;
            $addedEntries++;
        }
    }
    ksort($byDate);
    return [
        'trainings' => array_values($byDate),
        'removed_entries' => $removed['removed_entries'],
        'removed_days' => $removed['removed_days'],
        'added_entries' => $addedEntries,
        'added_days' => $addedDays,
    ];
}

/**
 * Count entries currently owned by an import source.
 */
function sparta_count_training_source_entries(array $trainings, string $sourceId): int
{
    $count = 0;
    foreach ($trainings as $trainingDay) {
        foreach ((array)($trainingDay['entries'] ?? []) as $entry) {
            if (is_array($entry) && (string)($entry['source_id'] ?? '') === $sourceId) {
                $count++;
            }
        }
    }
    return $count;
}

/**
 * Load trainings (shared), if present.
 *
 * @param string $dir
 * @param string|int|null $scope
 * @return array<int,array<string,mixed>>
 */
function load_trainings(string $dir, $scope = 'shared'): array
{
    $id = $scope ?? 'shared';
    $path = rtrim($dir, '/\\') . '/' . $id . '.json';
    if (!is_readable($path)) {
        return [];
    }
    $contents = file_get_contents($path);
    if ($contents === false || $contents === '') {
        return [];
    }
    $decoded = json_decode($contents, true);
    if (!is_array($decoded) || empty($decoded['trainings'])) {
        return [];
    }
    return is_array($decoded['trainings']) ? $decoded['trainings'] : [];
}

/**
 * Load per-athlete settings.
 *
 * @return array<string,mixed>
 */
function load_athlete_settings(string $dir, $athleteId): array
{
    $path = rtrim($dir, '/\\') . '/' . $athleteId . '.json';
    if (!is_readable($path)) {
        return [];
    }
    $contents = file_get_contents($path);
    if ($contents === false || $contents === '') {
        return [];
    }
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Save per-athlete settings.
 */
function save_athlete_settings(string $dir, $athleteId, array $settings): bool
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    $path = rtrim($dir, '/\\') . '/' . $athleteId . '.json';
    $json = json_encode($settings, JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

/**
 * Escape text for ICS fields.
 */
function escape_ics(string $value): string
{
    $value = str_replace("\\", "\\\\", $value);
    $value = str_replace("\n", "\\n", $value);
    $value = str_replace("\r", "", $value);
    $value = str_replace(",", "\\,", $value);
    $value = str_replace(";", "\\;", $value);
    return $value;
}

/**
 * Convert training data to an ICS calendar string.
 *
 * @param array<int,array<string,mixed>> $trainings
 */
function trainings_to_ics(array $trainings, array $tempoPaces = []): string
{
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//SpartaOne//Training Calendar//EN',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'X-WR-CALNAME:SpartaOne Training',
        'X-WR-TIMEZONE:UTC',
    ];

    foreach ($trainings as $trainingDay) {
        $date = $trainingDay['date'] ?? null;
        if (!$date) {
            continue;
        }
        $entries = $trainingDay['entries'] ?? [];
        if (!is_array($entries)) {
            $entries = [];
        }
        foreach ($entries as $idx => $entry) {
            $summary = $entry['title'] ?? '';
            if ($summary === '') {
                $summary = $entry['activity'] ?? 'Training';
            }
            $descriptionParts = [];
            if (!empty($entry['activity'])) {
                $descriptionParts[] = $entry['activity'];
            }
            if (!empty($entry['distance'])) {
                $descriptionParts[] = $entry['distance'];
            }
            if (!empty($entry['tempos']) && is_array($entry['tempos'])) {
                $descriptionParts[] = 'Tempos: ' . implode(', ', $entry['tempos']);
                $paceLines = [];
                foreach ($entry['tempos'] as $tempoLabel) {
                    if (!empty($tempoPaces[$tempoLabel])) {
                        $paceLines[] = $tempoLabel . ': ' . $tempoPaces[$tempoLabel];
                    }
                }
                if ($paceLines) {
                    $descriptionParts[] = implode("\n", $paceLines);
                }
            }
            if (!empty($entry['notes'])) {
                $descriptionParts[] = $entry['notes'];
            }
            $description = implode("\n", $descriptionParts);

            $startDay = DateTimeImmutable::createFromFormat('Y-m-d', (string)$date);
            if (!$startDay) {
                continue;
            }
            $weekday = (int)$startDay->format('N'); // 1=Mon ... 7=Sun
            $isEveningSession = in_array($weekday, [1, 3], true);

            if ($isEveningSession) {
                $start = $startDay->setTime(20, 15);
                $end = $startDay->setTime(22, 0);
            } else {
                $start = $startDay->setTime(9, 0);
                $end = $startDay->setTime(10, 30);
            }
            $uidSeed = $date . '-' . $idx . '-' . $summary;
            $uid = 'spartaone-' . md5($uidSeed) . '@spartaone.local';
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $uid;
            $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $start->format('Ymd\THis');
            $lines[] = 'DTEND:' . $end->format('Ymd\THis');
            $lines[] = 'SUMMARY:' . escape_ics((string)$summary);
            if ($isEveningSession) {
                $lines[] = 'LOCATION:' . escape_ics('AV Sparta (Voorburg), Groene Zoom 20, 2491 EH The Hague, Netherlands');
            }
            if ($description !== '') {
                $lines[] = 'DESCRIPTION:' . escape_ics($description);
            }
            $lines[] = 'END:VEVENT';
        }
    }

    $lines[] = 'END:VCALENDAR';
    return implode("\r\n", $lines) . "\r\n";
}
