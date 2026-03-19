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
        'athlete-age-grades' => 'sparta_handle_athlete_age_grades',
        'goals' => 'sparta_handle_goals',
        'training' => 'sparta_handle_training',
        'rankings' => 'sparta_handle_rankings',
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

/**
 * Build athlete metadata rows for training displays.
 *
 * @param array<int,array<string,mixed>> $athletes
 * @return array<int,array<string,mixed>>
 */
function sparta_build_training_athlete_rows(array $athletes, ?string $settingsDir): array
{
    $athleteRows = [];
    foreach ($athletes as $athlete) {
        $athleteId = (string)($athlete['id'] ?? '');
        if ($athleteId === '') {
            continue;
        }
        $athleteSettings = $settingsDir ? load_athlete_settings($settingsDir, $athleteId) : [];
        $athleteRows[] = [
            'id' => $athleteId,
            'name' => sparta_training_athlete_name($athlete),
            'gender' => sparta_training_gender_label($athleteSettings['gender'] ?? ''),
            'gender_key' => trim((string)($athleteSettings['gender'] ?? '')),
            'birthdate' => trim((string)($athleteSettings['birthdate'] ?? '')),
            'age' => sparta_training_age_from_birthdate($athleteSettings['birthdate'] ?? ''),
        ];
    }

    usort($athleteRows, static fn($a, $b) => strcasecmp((string)$a['name'], (string)$b['name']));

    return $athleteRows;
}

function sparta_handle_home(array $state, array $config, string $action): void
{
    $state['currentAction'] = $action;
    $state['upcomingGoals'] = []; // Defaults to empty when no goals exist.
    $state['athleteRows'] = sparta_build_training_athlete_rows(
        is_array($state['athletes'] ?? null) ? $state['athletes'] : [],
        $config['settingsDir'] ?? null
    );
    $trainingsDir = $config['trainingsDir'] ?? null;
    $rankingData = sparta_build_rankings_data($trainingsDir ? load_trainings($trainingsDir) : [], $state['athleteRows']);
    $state['homeOverallRanking'] = array_slice($rankingData['overallRanking'], 0, 3);
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

function sparta_handle_athlete_age_grades(array $state, array $config, string $action): void
{
    if (!sparta_require_login($state, 'Please log in to view athlete targets.')) {
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

    $settings = [];
    $settingsDir = $config['settingsDir'] ?? null;
    $isCurrent = $athleteId !== null
        && !empty($state['currentAthleteId'])
        && (string)$state['currentAthleteId'] === (string)$athleteId;
    if ($isCurrent) {
        $settings = is_array($state['athleteSettings'] ?? null) ? $state['athleteSettings'] : [];
    } elseif ($settingsDir && $athleteId) {
        $settings = load_athlete_settings($settingsDir, $athleteId);
    }

    $birthdate = trim((string)($settings['birthdate'] ?? ''));
    $gender = trim((string)($settings['gender'] ?? ''));
    $age = sparta_training_age_from_birthdate($birthdate);
    $supportedGender = sparta_age_grade_normalize_gender($gender);
    $targetTables = ($supportedGender !== '' && $age !== null)
        ? sparta_build_age_grade_target_tables($supportedGender, $age)
        : [];

    render('athlete-age-grades', [
        'athleteId' => $athleteId,
        'athlete' => $athleteDetail,
        'athleteSettings' => $settings,
        'athleteAge' => $age,
        'athleteGender' => $gender,
        'targetTables' => $targetTables,
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
    $athleteRows = is_array($athletes) ? sparta_build_training_athlete_rows($athletes, $settingsDir) : [];

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
                $mergeResult = sparta_merge_trainings_with_summary($trainings, $result['trainings']);
                $trainings = $mergeResult['trainings'];
                if (!save_trainings($trainingsDir, $trainings)) {
                    $trainingError = 'Unable to save trainings.';
                } else {
                    $summaryParts = [];
                    $summaryParts[] = (int)$mergeResult['added_entries'] . ' nieuwe training' . ((int)$mergeResult['added_entries'] === 1 ? '' : 'en') . ' toegevoegd';
                    if ((int)$mergeResult['added_days'] > 0) {
                        $summaryParts[] = 'op ' . (int)$mergeResult['added_days'] . ' ' . ((int)$mergeResult['added_days'] === 1 ? 'dag' : 'dagen');
                    }
                    if ((int)$mergeResult['skipped_duplicates'] > 0) {
                        $summaryParts[] = (int)$mergeResult['skipped_duplicates'] . ' duplic' . ((int)$mergeResult['skipped_duplicates'] === 1 ? 'aat' : 'aten') . ' overgeslagen';
                    }
                    $trainingMessage = implode(', ', $summaryParts) . '.';
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
        'athleteRows' => $athleteRows,
        'trainings' => $trainings ?? [],
        'trainingMessage' => $trainingMessage,
        'trainingError' => $trainingError,
        'tempoPaces' => $tempoPaces,
    ]);
}

/**
 * Build ranking page data from recorded race results.
 *
 * @param array<int,array<string,mixed>> $athleteRows
 * @return array{rankingDays: array<int,array<string,mixed>>, overallRanking: array<int,array<string,mixed>>, rankingWindows: array<int,array<string,mixed>>}
 */
function sparta_build_rankings_data(array $trainings, array $athleteRows): array
{
    $athleteMetaMap = [];
    foreach ($athleteRows as $athleteRow) {
        $athleteMetaMap[(string)$athleteRow['id']] = $athleteRow;
    }

    $rankingDays = [];
    $overallRanking = [];
    $now = new DateTimeImmutable('today');
    $rankingWindows = [
        ['label' => '0-12 maanden', 'weight' => 1.0],
        ['label' => '12-24 maanden', 'weight' => 0.75],
        ['label' => '24-36 maanden', 'weight' => 0.5],
    ];

    foreach ($trainings as $trainingDay) {
        $date = $trainingDay['date'] ?? '';
        $entries = $trainingDay['entries'] ?? [];
        if (!is_array($entries) || $date === '') {
            continue;
        }
        $eventDate = null;
        try {
            $eventDate = new DateTimeImmutable($date);
        } catch (Exception $e) {
            $eventDate = null;
        }

        $rankingEntries = [];
        foreach ($entries as $entry) {
            $raceType = sparta_normalize_training_race_type($entry['race_type'] ?? '');
            $finishTimes = sparta_normalize_training_finish_times($entry['finish_times'] ?? []);
            if ($raceType === '' || $finishTimes === []) {
                continue;
            }
            $entry['race_type'] = $raceType;
            $entry['finish_times'] = $finishTimes;
            $rankingEntries[] = $entry;

            if ($eventDate === null) {
                continue;
            }
            $eventAgeDays = (int)$eventDate->diff($now)->format('%r%a');
            if ($eventAgeDays < 0) {
                continue;
            }
            $eventAgeMonths = $eventDate->diff($now)->y * 12 + $eventDate->diff($now)->m;
            if ($eventAgeMonths < 12) {
                $windowWeight = 1.0;
            } elseif ($eventAgeMonths < 24) {
                $windowWeight = 0.75;
            } elseif ($eventAgeMonths < 36) {
                $windowWeight = 0.5;
            } else {
                continue;
            }
            $recencyWeight = sparta_ranking_recency_weight($eventAgeMonths);
            $effectiveWeight = $windowWeight * $recencyWeight;
            foreach ($finishTimes as $athleteId => $finishTime) {
                $athleteMeta = $athleteMetaMap[$athleteId] ?? null;
                if ($athleteMeta === null) {
                    continue;
                }
                $eventAge = sparta_training_age_from_birthdate_on_date($athleteMeta['birthdate'] ?? '', $date);
                $ageGrade = sparta_calculate_age_grade($raceType, $finishTime, $athleteMeta['gender_key'] ?? '', $eventAge);
                $percent = $ageGrade['percent'] ?? null;
                if (!is_numeric($percent)) {
                    continue;
                }
                if (!isset($overallRanking[$athleteId])) {
                    $overallRanking[$athleteId] = [
                        'id' => $athleteId,
                        'name' => (string)($athleteMeta['name'] ?? $athleteId),
                        'results' => [],
                        'result_count' => 0,
                        'latest_result_date' => $date,
                    ];
                }
                $overallRanking[$athleteId]['results'][] = [
                    'date' => $date,
                    'percent' => (float)$percent,
                    'weight' => $effectiveWeight,
                    'window_weight' => $windowWeight,
                    'recency_weight' => $recencyWeight,
                    'weighted_score' => (float)$percent * $effectiveWeight,
                ];
                $overallRanking[$athleteId]['result_count']++;
                if ($date > $overallRanking[$athleteId]['latest_result_date']) {
                    $overallRanking[$athleteId]['latest_result_date'] = $date;
                }
            }
        }

        if ($rankingEntries === []) {
            continue;
        }

        $rankingDays[] = [
            'date' => $date,
            'entries' => $rankingEntries,
            'view_state' => 'past',
            'allow_edit_links' => false,
        ];
    }

    usort($rankingDays, static function ($left, $right): int {
        return strcmp((string)($right['date'] ?? ''), (string)($left['date'] ?? ''));
    });

    $overallRanking = array_values(array_filter($overallRanking, static function ($row): bool {
        return !empty($row['results']) && is_array($row['results']);
    }));
    foreach ($overallRanking as &$rankingRow) {
        $bestScore = sparta_select_best_ranked_results($rankingRow['results']);
        $rankingRow['score'] = $bestScore['score'];
        $rankingRow['counted_results'] = $bestScore['counted_results'];
    }
    unset($rankingRow);
    usort($overallRanking, static function ($left, $right): int {
        $scoreCompare = ($right['score'] ?? 0) <=> ($left['score'] ?? 0);
        if ($scoreCompare !== 0) {
            return $scoreCompare;
        }
        $countCompare = ($right['result_count'] ?? 0) <=> ($left['result_count'] ?? 0);
        if ($countCompare !== 0) {
            return $countCompare;
        }
        return strcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
    });

    return [
        'rankingDays' => $rankingDays,
        'overallRanking' => $overallRanking,
        'rankingWindows' => $rankingWindows,
    ];
}

/**
 * Score athletes on the weighted average of their best 5 results.
 *
 * @param array<int,array<string,mixed>> $results
 * @return array{score: float, counted_results: int}
 */
function sparta_select_best_ranked_results(array $results): array
{
    if ($results === []) {
        return ['score' => 0.0, 'counted_results' => 0];
    }

    usort($results, static function ($left, $right): int {
        $weightedCompare = ((float)($right['weighted_score'] ?? 0.0)) <=> ((float)($left['weighted_score'] ?? 0.0));
        if ($weightedCompare !== 0) {
            return $weightedCompare;
        }
        $percentCompare = ((float)($right['percent'] ?? 0.0)) <=> ((float)($left['percent'] ?? 0.0));
        if ($percentCompare !== 0) {
            return $percentCompare;
        }
        return strcmp((string)($right['date'] ?? ''), (string)($left['date'] ?? ''));
    });

    $limit = min(5, count($results));
    $weightedTotal = 0.0;
    $weightTotal = 0.0;

    for ($i = 0; $i < $limit; $i++) {
        $weightedTotal += (float)($results[$i]['weighted_score'] ?? 0.0);
        $weightTotal += (float)($results[$i]['weight'] ?? 0.0);
    }

    if ($weightTotal <= 0.0) {
        return ['score' => 0.0, 'counted_results' => 0];
    }

    $baseScore = $weightedTotal / $weightTotal;
    return [
        'score' => $baseScore,
        'counted_results' => $limit,
    ];
}

function sparta_ranking_recency_weight(int $eventAgeMonths): float
{
    $boundedMonths = max(0, min(35, $eventAgeMonths));
    $maxPenalty = 0.06;
    $progress = $boundedMonths / 35;

    return 1.0 - ($maxPenalty * $progress);
}

function sparta_handle_rankings(array $state, array $config, string $action): void
{
    if (!sparta_require_login($state, 'Please log in to view rankings.')) {
        return;
    }

    $trainingsDir = $config['trainingsDir'] ?? null;
    $settingsDir = $config['settingsDir'] ?? null;
    $athletes = $state['athletes'] ?? [];
    $athleteRows = is_array($athletes) ? sparta_build_training_athlete_rows($athletes, $settingsDir) : [];
    $trainings = $trainingsDir ? load_trainings($trainingsDir) : [];
    $rankingData = sparta_build_rankings_data($trainings, $athleteRows);

    render('rankings', [
        'currentAthleteName' => $state['currentAthleteName'] ?? null,
        'currentAthleteId' => $state['currentAthleteId'] ?? null,
        'currentAthletePhoto' => $state['currentAthletePhoto'] ?? null,
        'currentAction' => $action,
        'athleteRows' => $athleteRows,
        'rankingDays' => $rankingData['rankingDays'],
        'overallRanking' => $rankingData['overallRanking'],
        'rankingWindows' => $rankingData['rankingWindows'],
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
