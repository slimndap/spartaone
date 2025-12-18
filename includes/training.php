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
