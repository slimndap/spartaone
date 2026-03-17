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
        'weekly-activities' => 'sparta_handle_weekly_activity_matrix',
        'tempos' => 'sparta_handle_tempos',
        'webhook' => 'sparta_handle_webhook',
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
        // Use array_merge so provided message/currentAction always override any defaults.
        render('home', array_merge($state, ['message' => $message, 'currentAction' => 'home']));
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
    $activities = [];
    $activitiesUpdatedAt = null;
    $activitiesDir = $config['activitiesDir'] ?? null;
    $settingsDir = $config['settingsDir'] ?? null;
    $trainingsDir = $config['trainingsDir'] ?? null;
    $tempoPaces = [];
    $trainings = [];
    if ($athleteId && !empty($state['athletes'])) {
        foreach ($state['athletes'] as $item) {
            if ((string)($item['id'] ?? '') === (string)$athleteId) {
                $athleteDetail = $item;
                break;
            }
        }
    }
    if ($athleteId) {
        $isCurrent = !empty($state['currentAthleteId']) && (string)$state['currentAthleteId'] === (string)$athleteId;
        if ($isCurrent && !empty($state['activities']) && is_array($state['activities'])) {
            $activities = $state['activities'];
            $activitiesUpdatedAt = $state['activitiesUpdatedAt'] ?? null;
            $tempoPaces = $state['tempoPaces'] ?? [];
        } elseif ($activitiesDir) {
            $cached = sparta_load_athlete_activities($activitiesDir, $athleteId);
            $activities = $cached['activities'] ?? [];
            $activitiesUpdatedAt = $cached['updated_at'] ?? null;
        }
        if ($trainingsDir) {
            $trainings = load_trainings($trainingsDir, $athleteId);
            if (!$trainings) {
                $trainings = load_trainings($trainingsDir);
            }
        }
        if (!$isCurrent && $settingsDir) {
            $settings = load_athlete_settings($settingsDir, $athleteId);
            $tempoPaces = resolve_tempo_paces($settings, $athleteId, $settingsDir, false);
        }
    }
    render('athlete-detail', [
        'athleteId' => $athleteId,
        'athlete' => $athleteDetail,
        'activities' => $activities,
        'activitiesUpdatedAt' => $activitiesUpdatedAt,
        'tempoPaces' => $tempoPaces,
        'trainings' => $trainings,
        'currentAthleteName' => $state['currentAthleteName'] ?? null,
        'currentAthleteId' => $state['currentAthleteId'] ?? null,
        'currentAthletePhoto' => $state['currentAthletePhoto'] ?? null,
        'currentAction' => $action,
    ]);
}

function sparta_handle_weekly_activity_matrix(array $state, array $config, string $action): void
{
    if (!sparta_require_login($state, 'Please log in to view activity insights.')) {
        return;
    }

    $activitiesDir = $config['activitiesDir'] ?? null;
    $settingsDir = $config['settingsDir'] ?? null;
    $trainingsDir = $config['trainingsDir'] ?? null;
    $rawAthletes = $state['athletes'] ?? [];
    $athleteColumns = [];
    foreach ($rawAthletes as $athlete) {
        $athleteId = (string)($athlete['id'] ?? '');
        if ($athleteId === '') {
            continue;
        }
        $fullName = trim(($athlete['firstname'] ?? '') . ' ' . ($athlete['lastname'] ?? ''));
        if ($fullName === '') {
            $fullName = $athlete['username'] ?? 'Onbekende sporter';
        }
        $athleteColumns[] = [
            'id' => $athleteId,
            'name' => $fullName,
            'photo' => $athlete['profile'] ?? null,
        ];
    }
    usort($athleteColumns, static fn($a, $b) => strcasecmp($a['name'], $b['name']));

    $today = new DateTimeImmutable('today');
    $startDay = $today->sub(new DateInterval('P13D'));
    $weekDays = [];
    for ($i = 0; $i < 14; $i++) {
        $day = $startDay->add(new DateInterval('P' . $i . 'D'));
        $dayKey = $day->format('Y-m-d');
        $weekDays[$dayKey] = [
            'label' => $day->format('D d M'),
            'date' => $dayKey,
        ];
    }

    $athleteIds = array_map(static fn($column) => $column['id'], $athleteColumns);
    foreach ($weekDays as &$day) {
        $day['athleteActivities'] = array_fill_keys($athleteIds, []);
    }
    unset($day);

    $activityCharts = [];
    $chartCounter = 0;
    $hasActivities = false;

    foreach ($athleteColumns as $column) {
        if (!$activitiesDir) {
            break;
        }
        $cached = sparta_load_athlete_activities($activitiesDir, $column['id']);
        $activities = $cached['activities'] ?? [];
        if ($activities === []) {
            continue;
        }
        usort($activities, static fn($a, $b) => strcmp(
            (string)($b['start_date_local'] ?? $b['start_date'] ?? ''),
            (string)($a['start_date_local'] ?? $a['start_date'] ?? '')
        ));

        $tempoPaces = [];
        if ($settingsDir) {
            $settings = load_athlete_settings($settingsDir, $column['id']);
            $tempoPaces = resolve_tempo_paces($settings, $column['id'], $settingsDir, false);
        }

        foreach ($activities as $activity) {
            $dateRaw = $activity['start_date_local'] ?? $activity['start_date'] ?? '';
            if ($dateRaw === '') {
                continue;
            }
            try {
                $dateObj = new DateTimeImmutable($dateRaw);
            } catch (Exception $e) {
                continue;
            }
            $dateKey = $dateObj->format('Y-m-d');
            if (!isset($weekDays[$dateKey])) {
                continue;
            }
            $hasActivities = true;

            $chartSuffix = $activity['id'] ?? null;
            if ($chartSuffix === null || $chartSuffix === '') {
                $chartSuffix = 'auto';
            }
            $chartSuffix = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)$chartSuffix);
            if ($chartSuffix === null || $chartSuffix === '') {
                $chartSuffix = 'auto';
            }
            $chartId = sprintf('activity-chart-%s-%s-%d', $column['id'], $chartSuffix, $chartCounter);
            $chartCounter++;
            $chartData = sparta_build_activity_chart_data($activity, $tempoPaces, $chartId);
            if ($chartData) {
                $activityCharts[] = $chartData;
            }
            $weekDays[$dateKey]['athleteActivities'][$column['id']][] = [
                'activity' => $activity,
                'chartId' => $chartId,
                'hasChart' => (bool)$chartData,
            ];
        }
    }

    $weekDays = array_values($weekDays);
    $weekDays = array_reverse($weekDays);
    $weekRangeLabel = $startDay->format('j M') . ' – ' . $today->format('j M');

    $scheduledTrainings = [];
    if ($trainingsDir) {
        $loadedTrainings = load_trainings($trainingsDir);
        foreach ($loadedTrainings as $trainingDay) {
            $dateKey = $trainingDay['date'] ?? null;
            if ($dateKey) {
                $scheduledTrainings[$dateKey] = $trainingDay['entries'] ?? [];
            }
        }
    }
    foreach ($weekDays as &$day) {
        $dateKey = $day['date'] ?? null;
        if ($dateKey && isset($scheduledTrainings[$dateKey])) {
            $day['trainingSchedule'] = $scheduledTrainings[$dateKey];
        } else {
            $day['trainingSchedule'] = [];
        }
    }
    unset($day);

    render('weekly-activities', [
        'athleteColumns' => $athleteColumns,
        'weekDays' => $weekDays,
        'weekRangeLabel' => $weekRangeLabel,
        'activityCharts' => $activityCharts,
        'hasActivities' => $hasActivities,
        'activitiesDir' => $activitiesDir,
        'trainingSchedule' => $scheduledTrainings,
        'layoutContainerClass' => 'container-fluid',
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
    $athletes = $state['athletes'] ?? [];

    if (!empty($state['isAdmin']) && isset($_POST['save_training'])) {
        $editDate = $_POST['edit_date'] ?? '';
        $editIdx = isset($_POST['edit_idx']) ? (int)$_POST['edit_idx'] : null;
        $title = trim((string)($_POST['title'] ?? ''));
        $activity = trim((string)($_POST['activity'] ?? ''));
        $distance = trim((string)($_POST['distance'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $raceType = sparta_normalize_training_race_type($_POST['race_type'] ?? '');
        $finishTimes = sparta_normalize_training_finish_times($_POST['finish_times'] ?? []);
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
            $trainingDay['entries'][$editIdx]['race_type'] = $raceType;
            $trainingDay['entries'][$editIdx]['finish_times'] = $raceType !== '' ? $finishTimes : [];
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
        'athletes' => $athletes,
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
    $profileMessage = null;
    $profileError = null;
    $genderOptions = [
        'male' => 'Man',
        'female' => 'Vrouw',
        'non_binary' => 'Non-binair',
        'other' => 'Anders',
        'prefer_not_to_say' => 'Zeg ik liever niet',
    ];
    $settings = sparta_load_settings_for_athlete($state, $settingsDir);
    $birthdate = '';
    $gender = '';

    if (!empty($settings)) {
        $baseFromSettings = compute_base_pace_seconds($settings);
        if ($baseFromSettings !== null) {
            $basePaceSeconds = $baseFromSettings;
            $basePaceInput = format_pace_seconds($basePaceSeconds);
        }
        if ($basePaceInput === '' && !empty($settings['base_pace_input'])) {
            $basePaceInput = (string)$settings['base_pace_input'];
        }
        if (!empty($settings['birthdate']) && is_string($settings['birthdate'])) {
            $birthdate = $settings['birthdate'];
        }
        if (!empty($settings['gender']) && is_string($settings['gender'])) {
            $gender = $settings['gender'];
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
    } elseif (isset($_POST['save_profile'])) {
        $birthdate = trim((string)($_POST['birthdate'] ?? ''));
        $gender = trim((string)($_POST['gender'] ?? ''));

        if ($birthdate !== '') {
            $birthdateObj = DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);
            if (!$birthdateObj || $birthdateObj->format('Y-m-d') !== $birthdate) {
                $profileError = 'Gebruik een geldige geboortedatum.';
            } elseif ($birthdateObj > new DateTimeImmutable('today')) {
                $profileError = 'De geboortedatum kan niet in de toekomst liggen.';
            }
        }

        if ($profileError === null && $gender !== '' && !isset($genderOptions[$gender])) {
            $profileError = 'Kies een geldige genderoptie.';
        }

        if ($profileError === null) {
            $settings['birthdate'] = $birthdate;
            $settings['gender'] = $gender;
            if (!save_athlete_settings($settingsDir, $state['currentAthleteId'], $settings)) {
                $profileError = 'Opslaan van profielgegevens is mislukt.';
            } else {
                $profileMessage = 'Profiel bijgewerkt.';
            }
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
        'birthdate' => $birthdate,
        'gender' => $gender,
        'genderOptions' => $genderOptions,
        'profileMessage' => $profileMessage,
        'profileError' => $profileError,
        'paceTable' => $paceTable,
        'selectedPaceRow' => $selectedPaceRow,
        'tempoPaces' => $tempoPaces,
    ]);
}
