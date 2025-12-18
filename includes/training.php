<?php

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

    $allowedTempos = ['3K', '5K', '10K', 'Half Marathon', 'Marathon', 'Aerobe', 'Recovery'];

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
                    'Return strictly JSON with this shape: {"trainings":[{"date":"YYYY-MM-DD","entries":[{"title":"string","activity":"string","distance":"string","notes":"string","tempos":["3K","5K","10K","Half Marathon","Marathon","Aerobe","Recovery"]}]}]}',
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
    return file_put_contents($path, $json, LOCK_EX) !== false;
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
 * Return pace reference table rows.
 *
 * @return array<int,array<string,string|int>>
 */
function get_pace_table(): array
{
    return [
        [
            'label' => '35 min (3:30/km)',
            'base' => 210,
            'five_k' => '3:23-3:26',
            'ten_k' => '3:30',
            'half_marathon' => '3:38-3:42',
            'marathon' => '3:48-3:55',
            'aerobe' => '4:25-5:00',
        ],
        [
            'label' => '38 min (3:48/km)',
            'base' => 228,
            'five_k' => '3:40-3:44',
            'ten_k' => '3:48',
            'half_marathon' => '3:57-4:02',
            'marathon' => '4:08-4:15',
            'aerobe' => '4:45-5:20',
        ],
        [
            'label' => '40 min (4:00/km)',
            'base' => 240,
            'five_k' => '3:52-3:56',
            'ten_k' => '4:00',
            'half_marathon' => '4:10-4:15',
            'marathon' => '4:20-4:30',
            'aerobe' => '5:00-5:35',
        ],
        [
            'label' => '43 min (4:18/km)',
            'base' => 258,
            'five_k' => '4:05-4:10',
            'ten_k' => '4:18',
            'half_marathon' => '4:28-4:35',
            'marathon' => '4:40-4:55',
            'aerobe' => '5:20-6:00',
        ],
        [
            'label' => '46 min (4:36/km)',
            'base' => 276,
            'five_k' => '4:20-4:25',
            'ten_k' => '4:36',
            'half_marathon' => '4:45-4:52',
            'marathon' => '5:00-5:10',
            'aerobe' => '5:40-6:20',
        ],
        [
            'label' => '49 min (4:54/km)',
            'base' => 294,
            'five_k' => '4:35-4:40',
            'ten_k' => '4:54',
            'half_marathon' => '5:05-5:12',
            'marathon' => '5:18-5:30',
            'aerobe' => '6:00-6:40',
        ],
        [
            'label' => '52 min (5:12/km)',
            'base' => 312,
            'five_k' => '4:55-5:00',
            'ten_k' => '5:12',
            'half_marathon' => '5:24-5:32',
            'marathon' => '5:40-5:55',
            'aerobe' => '6:20-7:00',
        ],
        [
            'label' => '55 min (5:30/km)',
            'base' => 330,
            'five_k' => '5:10-5:15',
            'ten_k' => '5:30',
            'half_marathon' => '5:42-5:52',
            'marathon' => '6:00-6:15',
            'aerobe' => '6:40-7:20',
        ],
    ];
}

/**
 * Pick nearest pace row for a base pace in seconds.
 *
 * @param array<int,array<string,mixed>> $paceTable
 * @return array<string,mixed>|null
 */
function find_pace_row(array $paceTable, int $baseSeconds): ?array
{
    $closest = null;
    $closestDiff = PHP_INT_MAX;
    foreach ($paceTable as $row) {
        $diff = abs(($row['base'] ?? 0) - $baseSeconds);
        if ($diff < $closestDiff) {
            $closest = $row;
            $closestDiff = $diff;
        }
    }
    return $closest;
}

/**
 * Map tempo labels to pace strings based on a selected row.
 *
 * @param array<string,mixed> $paceRow
 * @return array<string,string>
 */
function tempo_pace_map(array $paceRow): array
{
    if (empty($paceRow)) {
        return [];
    }
    return [
        '3K' => (string)($paceRow['five_k'] ?? ''),
        '5K' => (string)($paceRow['five_k'] ?? ''),
        '10K' => (string)($paceRow['ten_k'] ?? ''),
        'Half Marathon' => (string)($paceRow['half_marathon'] ?? ''),
        'Marathon' => (string)($paceRow['marathon'] ?? ''),
        'Aerobe' => (string)($paceRow['aerobe'] ?? ''),
        'Recovery' => (string)($paceRow['aerobe'] ?? ''),
    ];
}

/**
 * Parse a pace string in mm:ss to seconds.
 *
 * @return int|null seconds or null on error
 */
function parse_pace_to_seconds(string $pace, ?string &$error = null): ?int
{
    $pace = trim($pace);
    if ($pace === '') {
        $error = 'Tempo is leeg.';
        return null;
    }
    if (!preg_match('/^(\\d{1,2}):(\\d{2})$/', $pace, $m)) {
        $error = 'Gebruik het formaat mm:ss (bijv. 04:30).';
        return null;
    }
    $minutes = (int)$m[1];
    $seconds = (int)$m[2];
    if ($seconds >= 60) {
        $error = 'Seconden moeten onder 60 zijn.';
        return null;
    }
    return $minutes * 60 + $seconds;
}

/**
 * Format seconds into mm:ss.
 */
function format_pace_seconds(int $seconds): string
{
    $seconds = max(0, $seconds);
    $m = floor($seconds / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d', $m, $s);
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
