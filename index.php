<?php
session_start();

$envFile = __DIR__ . '/.env';
require_once __DIR__ . '/includes/env.php';
sparta_load_env($envFile);

$clientId = sparta_env('STRAVA_CLIENT_ID');
$clientSecret = sparta_env('STRAVA_CLIENT_SECRET');
$redirectUri = sparta_env('STRAVA_REDIRECT_URI');
$authBase = 'https://www.strava.com/oauth/authorize';
$tokenUrl = 'https://www.strava.com/api/v3/oauth/token';
$scope = 'read,activity:read';
$athletesDir = __DIR__ . '/data/athletes';
$goalsDir = __DIR__ . '/data/goals';
$trainingsDir = __DIR__ . '/data/trainings';
$settingsDir = __DIR__ . '/data/settings';

require_once __DIR__ . '/includes/training.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/includes/paces.php';
require_once __DIR__ . '/includes/goal-render.php';
require_once __DIR__ . '/includes/strava.php';
require_once __DIR__ . '/includes/view.php';
$state = buildStravaState($_GET, $_POST, $_SESSION, [
    'clientId' => $clientId,
    'clientSecret' => $clientSecret,
    'redirectUri' => $redirectUri,
    'authBase' => $authBase,
    'tokenUrl' => $tokenUrl,
    'scope' => $scope,
    'athletesDir' => $athletesDir,
    'goalsDir' => $goalsDir,
    'trainingsDir' => $trainingsDir,
    'settingsDir' => $settingsDir,
]);

$settings = $state['athleteSettings'] ?? [];
$tempoPaces = resolve_tempo_paces($settings, $state['currentAthleteId'] ?? null, $settingsDir, true);
$state['tempoPaces'] = $tempoPaces;

$action = $_GET['action'] ?? 'home';
switch ($action) {
    case 'athletes':
        if (empty($state['currentAthleteId'])) {
            render('home', $state + ['message' => 'Please log in to view athletes.']);
        } else {
            render('athletes', [
                'otherAthletes' => $state['otherAthletes'] ?? [],
                'currentAthleteName' => $state['currentAthleteName'] ?? null,
                'currentAthleteId' => $state['currentAthleteId'] ?? null,
                'currentAthletePhoto' => $state['currentAthletePhoto'] ?? null,
                'currentAction' => $action,
                'storageError' => $state['storageError'] ?? null,
            ]);
        }
        break;
    case 'training':
        // Always allow ICS subscription without requiring a login.
        if (isset($_GET['format']) && $_GET['format'] === 'ics') {
            $icsSettings = $state['athleteSettings'] ?? [];
            $tempoPacesIcs = resolve_tempo_paces($icsSettings, $_GET['athlete'] ?? null, $settingsDir, false);
            $trainings = load_trainings($trainingsDir);
            $ics = trainings_to_ics($trainings, $tempoPacesIcs);
            header('Content-Type: text/calendar; charset=utf-8');
            header('Content-Disposition: inline; filename="spartaone-training.ics"');
            echo $ics;
            exit;
        }

        if (empty($state['currentAthleteId'])) {
            render('home', $state + ['message' => 'Please log in to view the training schedule.']);
        } else {
            $trainingMessage = null;
            $trainingError = null;
            $trainings = load_trainings($trainingsDir);
            $settings = $state['athleteSettings'] ?? [];
            if ($settingsDir && empty($settings) && !empty($state['currentAthleteId'])) {
                $settings = load_athlete_settings($settingsDir, $state['currentAthleteId']);
            }
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
        break;
    case 'tempos':
        if (empty($state['currentAthleteId'])) {
            render('home', $state + ['message' => 'Please log in to view tempos.']);
        } else {
            $basePaceInput = '';
            $basePaceSeconds = null;
            $basePaceError = null;
            $settings = $state['athleteSettings'] ?? [];

            // Fallback: load settings directly if state didn't have them.
            if ($settingsDir && empty($settings) && !empty($state['currentAthleteId'])) {
                $settings = load_athlete_settings($settingsDir, $state['currentAthleteId']);
            }

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
                    save_athlete_settings($settingsDir, $state['currentAthleteId'], [
                        'base_pace_seconds' => $basePaceSeconds,
                        'base_pace_input' => $basePaceInput,
                    ]);
                }
            } elseif (isset($_POST['base_pace'])) {
                $basePaceInput = trim((string)$_POST['base_pace']);
                $parsed = parse_pace_to_seconds($basePaceInput, $basePaceError);
                if ($parsed !== null) {
                    $basePaceSeconds = $parsed;
                    save_athlete_settings($settingsDir, $state['currentAthleteId'], [
                        'base_pace_seconds' => $basePaceSeconds,
                        'base_pace_input' => $basePaceInput,
                    ]);
                }
            }

            $paceTable = get_pace_table();

            $selectedPaceRow = null;
            if ($basePaceSeconds !== null) {
                $selectedPaceRow = find_pace_row($paceTable, $basePaceSeconds);
            }

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
        break;
    case 'athlete':
        if (empty($state['currentAthleteId'])) {
            render('home', $state + ['message' => 'Please log in to view athletes.']);
        } else {
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
        break;
    case 'goals':
        if (empty($state['currentAthleteId'])) {
            render('home', $state + ['message' => 'Please log in to edit goals.']);
        } else {
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
        break;
    default:
        $state['currentAction'] = $action;
        $state['upcomingGoals'] = [];
        if (!empty($state['athlete']['goals']) && is_array($state['athlete']['goals'])) {
            $state['upcomingGoals'] = sparta_next_goals($state['athlete']['goals'], 3);
        }
        render('home', $state);
        break;
}
$tempoPaces = [];
$settings = $state['athleteSettings'] ?? [];
if ($settingsDir && empty($settings) && !empty($state['currentAthleteId'])) {
    $settings = load_athlete_settings($settingsDir, $state['currentAthleteId']);
}
$basePaceSecondsGlobal = isset($settings['base_pace_seconds']) ? (int)$settings['base_pace_seconds'] : null;
if ($basePaceSecondsGlobal !== null) {
    $paceRowGlobal = find_pace_row(get_pace_table(), $basePaceSecondsGlobal);
    $tempoPaces = tempo_pace_map($paceRowGlobal ?: []);
}
