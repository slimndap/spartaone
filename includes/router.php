<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Dispatch incoming action to the appropriate route handler.
 *
 * @param string $action Query param action name, defaults to home when empty.
 * @param array<string,mixed> $state Hydrated request/session state.
 * @param array<string,string> $config App configuration values.
 */
function sparta_handle_request(string $action, array $state, array $config): void
{
    $action = $action ?: 'home';
    $handlers = [
        'athletes' => 'sparta_handle_athletes',
        'athlete' => 'sparta_handle_athlete_detail',
        'goals' => 'sparta_handle_goals',
        'training' => 'sparta_handle_training',
        'tempos' => 'sparta_handle_tempos',
        'home' => 'sparta_handle_home',
    ];
    $handler = $handlers[$action] ?? $handlers['home']; // Fallback to home for unknown actions.
    $handler($state, $config, $action);
}

/**
 * Gate protected routes behind a login check.
 *
 * @param array<string,mixed> $state
 * @param string $message Message to show when redirecting to home.
 * @return bool True if access allowed.
 */
function sparta_require_login(array $state, string $message): bool
{
    if (empty($state['currentAthleteId'])) {
        render('home', $state + ['message' => $message, 'currentAction' => 'home']);
        return false;
    }
    return true;
}

function sparta_load_settings_for_athlete(array $state, ?string $settingsDir): array
{
    $settings = $state['athleteSettings'] ?? [];
    if ($settingsDir && empty($settings) && !empty($state['currentAthleteId'])) {
        $settings = load_athlete_settings($settingsDir, $state['currentAthleteId']);
    }
    return $settings;
}

function sparta_handle_home(array $state, array $config, string $action): void
{
    $state['currentAction'] = $action;
    $state['upcomingGoals'] = []; // Defaults to empty when no goals exist.
    if (!empty($state['athlete']['goals']) && is_array($state['athlete']['goals'])) {
        $state['upcomingGoals'] = sparta_next_goals($state['athlete']['goals'], 3);
    }
    render('home', $state);
}

function sparta_handle_athletes(array $state, array $config, string $action): void
{
    if (!sparta_require_login($state, 'Please log in to view athletes.')) {
        return;
    }
    render('athletes', [
        'otherAthletes' => $state['otherAthletes'] ?? [],
        'currentAthleteName' => $state['currentAthleteName'] ?? null,
        'currentAthleteId' => $state['currentAthleteId'] ?? null,
        'currentAthletePhoto' => $state['currentAthletePhoto'] ?? null,
        'currentAction' => $action,
        'storageError' => $state['storageError'] ?? null,
    ]);
}

function sparta_handle_athlete_detail(array $state, array $config, string $action): void
{
    if (!sparta_require_login($state, 'Please log in to view athletes.')) {
        return;
    }
    $athleteId = $_GET['id'] ?? null;
    $athleteDetail = null;
    if ($athleteId && !empty($state['athletes'])) {
        foreach ($state['athletes'] as $item) {
            if ((string)($item['id'] ?? '') === (string)$athleteId) {
                $athleteDetail = $item;
                break;
            }
        }
    }
    render('athlete-detail', [
        'athleteId' => $athleteId,
        'athlete' => $athleteDetail,
        'currentAthleteName' => $state['currentAthleteName'] ?? null,
        'currentAthleteId' => $state['currentAthleteId'] ?? null,
        'currentAthletePhoto' => $state['currentAthletePhoto'] ?? null,
        'currentAction' => $action,
    ]);
}

function sparta_handle_goals(array $state, array $config, string $action): void
{
    if (!sparta_require_login($state, 'Please log in to edit goals.')) {
        return;
    }
    render('goals', [
        'athletes' => $state['athletes'] ?? [],
        'athlete' => $state['athlete'] ?? null,
        'goals' => $state['athlete']['goals'] ?? [],
        'currentAthleteId' => $state['currentAthleteId'] ?? null,
        'currentAthleteName' => $state['currentAthleteName'] ?? null,
        'currentAthletePhoto' => $state['currentAthletePhoto'] ?? null,
        'currentAction' => $action,
        'goalMessage' => $state['goalMessage'] ?? null,
        'goalError' => $state['goalError'] ?? null,
        'saveGoalsSuccess' => $state['saveGoalsSuccess'] ?? null,
        'storageError' => $state['storageError'] ?? null,
    ]);
}

function sparta_handle_training(array $state, array $config, string $action): void
{
    $trainingsDir = $config['trainingsDir'];
    $settingsDir = $config['settingsDir'];

    // Public ICS feed does not require login.
    if (isset($_GET['format']) && $_GET['format'] === 'ics') {
        $icsSettings = $state['athleteSettings'] ?? [];
        $tempoPacesIcs = resolve_tempo_paces($icsSettings, $_GET['athlete'] ?? null, $settingsDir, false);
        $trainings = load_trainings($trainingsDir);
        $ics = trainings_to_ics($trainings, $tempoPacesIcs);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="spartaone-training.ics"');
        echo $ics;
        return;
    }

    if (!sparta_require_login($state, 'Please log in to view the training schedule.')) {
        return;
    }

    $trainingMessage = null;
    $trainingError = null;
    $trainings = load_trainings($trainingsDir);
    $settings = sparta_load_settings_for_athlete($state, $settingsDir);
    $tempoPaces = resolve_tempo_paces($settings, $state['currentAthleteId'] ?? null, $settingsDir, true);

    if (!empty($state['isAdmin']) && isset($_POST['save_training'])) {
        $editDate = $_POST['edit_date'] ?? '';
        $editIdx = isset($_POST['edit_idx']) ? (int)$_POST['edit_idx'] : null;
        $title = trim((string)($_POST['title'] ?? ''));
        $activity = trim((string)($_POST['activity'] ?? ''));
        $distance = trim((string)($_POST['distance'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $temposList = [];
        if (!empty($_POST['tempos']) && is_array($_POST['tempos'])) {
            foreach ($_POST['tempos'] as $t) {
                $t = trim((string)$t);
                if ($t !== '') {
                    $temposList[] = $t;
                }
            }
        }
        $updated = false;
        foreach ($trainings as &$trainingDay) {
            if (($trainingDay['date'] ?? '') !== $editDate) {
                continue;
            }
            if (!isset($trainingDay['entries'][$editIdx])) {
                continue;
            }
            $trainingDay['entries'][$editIdx]['title'] = $title;
            $trainingDay['entries'][$editIdx]['activity'] = $activity;
            $trainingDay['entries'][$editIdx]['distance'] = $distance;
            $trainingDay['entries'][$editIdx]['notes'] = $notes;
            $trainingDay['entries'][$editIdx]['tempos'] = $temposList;
            $updated = true;
            break;
        }
        unset($trainingDay);
        if ($updated) {
            if (!save_trainings($trainingsDir, $trainings)) {
                $trainingError = 'Unable to save updated training.';
            } else {
                $trainingMessage = 'Training bijgewerkt.';
            }
        } else {
            $trainingError = 'Training niet gevonden om te wijzigen.';
        }
    }

    if (!empty($_POST['training_csv'])) {
        $csvText = trim((string)$_POST['training_csv']);
        if ($csvText === '') {
            $trainingError = 'Please paste CSV data before submitting.';
        } else {
            $result = parse_training_csv_with_openai($csvText);
            if (!empty($result['error'])) {
                $trainingError = $result['error'];
            } elseif (empty($result['trainings'])) {
                $trainingError = 'No trainings returned from the CSV.';
            } else {
                $trainings = $result['trainings'];
                if (!save_trainings($trainingsDir, $trainings)) {
                    $trainingError = 'Unable to save trainings.';
                } else {
                    $trainingMessage = 'Training schedule saved.';
                }
            }
        }
    }

    render('training', [
        'currentAthleteName' => $state['currentAthleteName'] ?? null,
        'currentAthleteId' => $state['currentAthleteId'] ?? null,
        'currentAthletePhoto' => $state['currentAthletePhoto'] ?? null,
        'currentAction' => $action,
        'isAdmin' => $state['isAdmin'] ?? false,
        'trainings' => $trainings ?? [],
        'trainingMessage' => $trainingMessage,
        'trainingError' => $trainingError,
        'tempoPaces' => $tempoPaces,
    ]);
}

function sparta_handle_tempos(array $state, array $config, string $action): void
{
    $settingsDir = $config['settingsDir'];
    if (!sparta_require_login($state, 'Please log in to view tempos.')) {
        return;
    }

    $basePaceInput = '';
    $basePaceSeconds = null;
    $basePaceError = null;
    $settings = sparta_load_settings_for_athlete($state, $settingsDir);

    if (!empty($settings)) {
        if (isset($settings['base_pace_seconds'])) {
            $basePaceSeconds = (int)$settings['base_pace_seconds'];
            $basePaceInput = format_pace_seconds($basePaceSeconds);
        }
        if ($basePaceInput === '' && !empty($settings['base_pace_input'])) {
            $basePaceInput = (string)$settings['base_pace_input'];
        }
    }
    if (isset($_POST['base_pace_seconds'])) {
        $rawSeconds = (int)$_POST['base_pace_seconds'];
        if ($rawSeconds < 180 || $rawSeconds > 330) {
            $basePaceError = 'Kies een tempo tussen 03:00 en 05:30.';
        } else {
            $basePaceSeconds = $rawSeconds;
            $basePaceInput = format_pace_seconds($basePaceSeconds);
            $settings['base_pace_seconds'] = $basePaceSeconds;
            $settings['base_pace_input'] = $basePaceInput;
            save_athlete_settings($settingsDir, $state['currentAthleteId'], $settings);
        }
    } elseif (isset($_POST['base_pace'])) {
        $basePaceInput = trim((string)$_POST['base_pace']);
        $parsed = parse_pace_to_seconds($basePaceInput, $basePaceError);
        if ($parsed !== null) {
            $basePaceSeconds = $parsed;
            $settings['base_pace_seconds'] = $basePaceSeconds;
            $settings['base_pace_input'] = $basePaceInput;
            save_athlete_settings($settingsDir, $state['currentAthleteId'], $settings);
        }
    }

    $paceTable = get_pace_table();
    $selectedPaceRow = $basePaceSeconds !== null
        ? find_pace_row($paceTable, $basePaceSeconds)
        : null;
    $tempoPaces = resolve_tempo_paces($settings, $state['currentAthleteId'] ?? null, $settingsDir, true);

    render('tempos', [
        'currentAthleteName' => $state['currentAthleteName'] ?? null,
        'currentAthleteId' => $state['currentAthleteId'] ?? null,
        'currentAthletePhoto' => $state['currentAthletePhoto'] ?? null,
        'currentAction' => $action,
        'basePaceInput' => $basePaceInput,
        'basePaceSeconds' => $basePaceSeconds,
        'basePaceError' => $basePaceError,
        'paceTable' => $paceTable,
        'selectedPaceRow' => $selectedPaceRow,
        'tempoPaces' => $tempoPaces,
    ]);
}
