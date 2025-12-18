<?php

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
    $aeroob = (string)($paceRow['aerobe'] ?? '');
    return [
        '3K' => (string)($paceRow['five_k'] ?? ''),
        '5K' => (string)($paceRow['five_k'] ?? ''),
        '10K' => (string)($paceRow['ten_k'] ?? ''),
        'Half Marathon' => (string)($paceRow['half_marathon'] ?? ''),
        'Marathon' => (string)($paceRow['marathon'] ?? ''),
        'Aeroob' => $aeroob,
        // Legacy label support
        'Aerobe' => $aeroob,
        'Recovery' => $aeroob,
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
 * Compute base pace seconds from settings that may contain raw seconds or a mm:ss input string.
 */
function compute_base_pace_seconds(array $settings): ?int
{
    if (isset($settings['base_pace_seconds'])) {
        return (int)$settings['base_pace_seconds'];
    }
    if (!empty($settings['base_pace_input'])) {
        return parse_pace_to_seconds((string)$settings['base_pace_input']);
    }
    return null;
}

/**
 * Resolve the nearest pace row given athlete settings and a pace table.
 */
function pace_row_from_settings(array $settings, array $paceTable): ?array
{
    $baseSeconds = compute_base_pace_seconds($settings);
    if ($baseSeconds === null) {
        return null;
    }
    return find_pace_row($paceTable, $baseSeconds);
}

/**
 * Resolve tempo paces for an athlete using the provided settings. When $persistContext
 * is true, the computed tempos are cached for later use in rendering.
 */
function resolve_tempo_paces(
    array $athleteSettings = [],
    $athleteId = null,
    ?string $settingsDir = null,
    bool $persistContext = true
): array {
    if (!$athleteSettings && $settingsDir && $athleteId) {
        $athleteSettings = load_athlete_settings($settingsDir, $athleteId);
    }

    $baseSeconds = isset($athleteSettings['base_pace_seconds'])
        ? (int)$athleteSettings['base_pace_seconds']
        : null;

    if ($baseSeconds === null) {
        if ($persistContext) {
            $GLOBALS['tempoPacesContext'] = [];
        }
        return [];
    }

    $paceRow = find_pace_row(get_pace_table(), $baseSeconds);
    $tempoPaces = tempo_pace_map($paceRow ?: []);

    if ($persistContext) {
        $GLOBALS['tempoPacesContext'] = $tempoPaces;
    }

    return $tempoPaces;
}

/**
 * Fetch the cached tempo pace lookup for rendering.
 */
function get_tempo_paces_context(): array
{
    return $GLOBALS['tempoPacesContext'] ?? [];
}
