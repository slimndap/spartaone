<?php

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
