<?php
$trainingsByDate = [];
$raceOptions = function_exists('sparta_training_race_options') ? sparta_training_race_options() : [];
$athleteRows = (isset($athleteRows) && is_array($athleteRows)) ? $athleteRows : [];
if (!empty($trainings) && is_array($trainings)) {
    foreach ($trainings as $trainingDay) {
        $dateStr = $trainingDay['date'] ?? null;
        if (!$dateStr) {
            continue;
        }
        $dateKey = null;
        try {
            $dateKey = (new DateTimeImmutable($dateStr))->format('Y-m-d');
        } catch (Exception $e) {
            continue;
        }
        $trainingsByDate[$dateKey] = $trainingDay['entries'] ?? [];
    }
}

$viewState = isset($_GET['state']) && in_array($_GET['state'], ['upcoming', 'past'], true) ? $_GET['state'] : 'upcoming';
$isPastView = $viewState === 'past';
$editDate = $_GET['edit_date'] ?? null;
$editIdx = isset($_GET['edit_idx']) ? (int)$_GET['edit_idx'] : null;

$icsQuery = 'action=training&format=ics';
$scriptPath = !empty($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';
$icsPath = $scriptPath . '?' . $icsQuery;
$icsUrl = null;
$webcalUrl = null;
if (!empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $icsUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $icsPath;
    if (!empty($currentAthleteId)) {
        $icsUrl .= '&athlete=' . urlencode($currentAthleteId);
    }
    $webcalUrl = preg_replace('~^https?~', 'webcal', $icsUrl);
}
?>

<div class="training-shell">
<?php if ($webcalUrl): ?>
    <a class="btn btn-primary d-inline-flex align-items-center gap-1 w-100 mb-3 training-subscribe" href="<?php echo htmlspecialchars($webcalUrl, ENT_QUOTES, 'UTF-8'); ?>">
        <i class="bi bi-calendar2-event"></i>
        <span>Toevoegen aan agenda</span>
    </a>
<?php endif; ?>
<div class="card shadow-sm mb-4 glass-card training-card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
            <div>
                <div class="section-label mb-1">Schema</div>
                <div class="fw-semibold"><?php echo $isPastView ? 'Verleden' : 'Aankomend'; ?></div>
            </div>
            <div class="btn-group" role="tablist" aria-label="Training filter">
                <a class="btn btn-sm <?php echo $isPastView ? 'btn-outline-secondary' : 'btn-primary'; ?>" href="?action=training&state=upcoming">Aankomend</a>
                <a class="btn btn-sm <?php echo $isPastView ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="?action=training&state=past">Verleden</a>
            </div>
        </div>
        <div>
            <?php
            $sortedDates = array_keys($trainingsByDate);
            if ($isPastView) {
                rsort($sortedDates);
            } else {
                sort($sortedDates);
            }
$todayStr = (new DateTimeImmutable('today'))->format('Y-m-d');
foreach ($sortedDates as $dateKey):
    if (!$isPastView && $dateKey < $todayStr) {
        continue;
    }
    if ($isPastView && $dateKey >= $todayStr) {
        continue;
    }
    $entries = $trainingsByDate[$dateKey] ?? [];
    if (!$entries) {
        continue;
    }
?>
                <div class="training-day">
        <div class="training-list">
            <?php
                render_partial('cards/training_day', [
                    'trainingDay' => [
                        'date' => $dateKey,
                        'entries' => $entries,
                        'view_state' => $viewState,
                    ],
                    'athleteRows' => $athleteRows,
                ]);
            ?>
        </div>
        <?php if (!empty($isAdmin) && $editDate === $dateKey && isset($entries[$editIdx])): ?>
            <?php $entry = $entries[$editIdx]; ?>
            <?php $selectedRaceType = function_exists('sparta_normalize_training_race_type') ? sparta_normalize_training_race_type($entry['race_type'] ?? '') : ''; ?>
            <?php $finishTimes = function_exists('sparta_normalize_training_finish_times') ? sparta_normalize_training_finish_times($entry['finish_times'] ?? []) : []; ?>
            <form method="post" action="?action=training&state=<?php echo htmlspecialchars($viewState, ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-2 mt-2">
                <input type="hidden" name="edit_date" value="<?php echo htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="edit_idx" value="<?php echo (int)$editIdx; ?>">
                <input type="hidden" name="save_training" value="1">
                <div class="mb-2">
                    <label class="form-label form-label-sm mb-1">Titel</label>
                    <input type="text" name="title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($entry['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label form-label-sm mb-1">Activiteit</label>
                    <input type="text" name="activity" class="form-control form-control-sm" value="<?php echo htmlspecialchars($entry['activity'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label form-label-sm mb-1">Afstand</label>
                    <input type="text" name="distance" class="form-control form-control-sm" value="<?php echo htmlspecialchars($entry['distance'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label form-label-sm mb-1">Wedstrijdtype</label>
                    <select name="race_type" class="form-select form-select-sm">
                        <option value="">Geen wedstrijd</option>
                        <?php foreach ($raceOptions as $raceValue => $raceLabel): ?>
                            <option value="<?php echo htmlspecialchars($raceValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedRaceType === $raceValue ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($raceLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label form-label-sm mb-1">Tempos</label>
                    <?php
                    $availableTempos = ['3K', '5K', '10K', 'Half Marathon', 'Marathon', 'Aeroob', 'Recovery'];
                    $entryTempos = $entry['tempos'] ?? [];
                    foreach ($availableTempos as $tempoOption): ?>
                        <div class="form-check form-check-sm">
                            <input class="form-check-input" type="checkbox" name="tempos[]" value="<?php echo htmlspecialchars($tempoOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (in_array($tempoOption, $entryTempos, true) || ($tempoOption === 'Aeroob' && in_array('Aerobe', $entryTempos, true))) ? 'checked' : ''; ?> id="tempo_<?php echo htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8'); ?>_<?php echo (int)$editIdx; ?>_<?php echo htmlspecialchars($tempoOption, ENT_QUOTES, 'UTF-8'); ?>">
                            <label class="form-check-label" for="tempo_<?php echo htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8'); ?>_<?php echo (int)$editIdx; ?>_<?php echo htmlspecialchars($tempoOption, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tempoOption, ENT_QUOTES, 'UTF-8'); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mb-2">
                    <label class="form-label form-label-sm mb-1">Notities</label>
                    <textarea name="notes" class="form-control form-control-sm" rows="2"><?php echo htmlspecialchars($entry['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label form-label-sm mb-1">Finishtijden per sporter</label>
                    <div class="form-text mb-2">Vul alleen tijden in voor wedstrijden.</div>
                    <?php if (empty($athleteRows)): ?>
                        <div class="text-muted small">Geen sporters beschikbaar.</div>
                    <?php else: ?>
                        <div class="row g-2">
                            <?php foreach ($athleteRows as $athleteRow): ?>
                                <div class="col-12 col-md-6">
                                    <label class="form-label form-label-sm mb-1" for="finish_time_<?php echo htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8'); ?>_<?php echo (int)$editIdx; ?>_<?php echo htmlspecialchars($athleteRow['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($athleteRow['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </label>
                                    <input
                                        type="text"
                                        name="finish_times[<?php echo htmlspecialchars($athleteRow['id'], ENT_QUOTES, 'UTF-8'); ?>]"
                                        id="finish_time_<?php echo htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8'); ?>_<?php echo (int)$editIdx; ?>_<?php echo htmlspecialchars($athleteRow['id'], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="form-control form-control-sm"
                                        placeholder="bijv. 00:42:15"
                                        value="<?php echo htmlspecialchars($finishTimes[$athleteRow['id']] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="?action=training&state=<?php echo htmlspecialchars($viewState, ENT_QUOTES, 'UTF-8'); ?>">Annuleer</a>
                    <button type="submit" class="btn btn-primary btn-sm">Opslaan</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
        </div>
    </div>
</div>
</div>

<?php if (!empty($isAdmin)): ?>
    <div class="card shadow-sm glass-card">
        <div class="card-body">
            <h1 class="h4 mb-3">Schema</h1>
            <?php if (!empty($trainingMessage)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($trainingMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (!empty($trainingError)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($trainingError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php
            $larsImportSource = (isset($larsImportSource) && is_array($larsImportSource)) ? $larsImportSource : [];
            $trainingImportPreview = (isset($trainingImportPreview) && is_array($trainingImportPreview)) ? $trainingImportPreview : [];
            $larsImportedCount = isset($larsImportedCount) ? (int)$larsImportedCount : 0;
            ?>
            <div class="border rounded p-3 mb-4">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
                    <div>
                        <div class="section-label mb-1">Bronimport</div>
                        <div class="fw-semibold">Marathon Den Haag 2026</div>
                        <?php if (empty($larsImportSource['error'])): ?>
                            <div class="text-muted small">
                                <?php echo (int)($larsImportSource['source_count'] ?? 0); ?> sessies,
                                <?php echo htmlspecialchars((string)($larsImportSource['start_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                t/m
                                <?php echo htmlspecialchars((string)($larsImportSource['end_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="badge text-bg-secondary"><?php echo $larsImportedCount; ?> geïmporteerd</span>
                </div>

                <?php if (!empty($larsImportSource['error'])): ?>
                    <div class="alert alert-danger mb-0"><?php echo htmlspecialchars((string)$larsImportSource['error'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                    <?php if ($trainingImportPreview): ?>
                        <div class="alert alert-info">
                            Voorbeeld: <?php echo (int)$trainingImportPreview['removed_entries']; ?> bestaande bronitems worden vervangen
                            door <?php echo (int)$trainingImportPreview['added_entries']; ?> sessies.
                            Het gedeelde schema komt uit op <?php echo (int)$trainingImportPreview['total_days']; ?> dagen
                            en <?php echo (int)$trainingImportPreview['total_entries']; ?> trainingsitems.
                        </div>
                    <?php endif; ?>
                    <p class="text-muted small">
                        Opnieuw importeren vervangt alleen items met bron
                        <code>lars-den-haag-2026</code>. Andere gedeelde trainingen blijven staan en vooraf wordt een backup gemaakt.
                    </p>
                    <div class="d-flex gap-2 flex-wrap">
                        <form method="post" action="?action=training">
                            <input type="hidden" name="lars_import_action" value="preview">
                            <button type="submit" class="btn btn-outline-light btn-sm">Voorbeeld</button>
                        </form>
                        <form method="post" action="?action=training">
                            <input type="hidden" name="lars_import_action" value="replace">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <?php echo $larsImportedCount > 0 ? 'Opnieuw importeren' : 'Importeren'; ?>
                            </button>
                        </form>
                        <?php if ($larsImportedCount > 0): ?>
                            <form method="post" action="?action=training" onsubmit="return confirm('Alleen de geïmporteerde Den Haag-trainingen verwijderen? Er wordt eerst een backup gemaakt.');">
                                <input type="hidden" name="lars_import_action" value="remove">
                                <button type="submit" class="btn btn-outline-danger btn-sm">Import verwijderen</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="post" action="?action=training" class="mb-4">
                <div class="mb-3">
                    <label for="training_csv" class="form-label">Paste training CSV</label>
                    <textarea class="form-control" id="training_csv" name="training_csv" rows="6" placeholder="Date,Activity,Distance,Notes"><?php echo isset($_POST['training_csv']) ? htmlspecialchars($_POST['training_csv'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                    <div class="form-text">CSV will be parsed via OpenAI and saved per date.</div>
                </div>
                <button type="submit" class="btn btn-primary">Process &amp; Save</button>
            </form>

        </div>
    </div>
<?php endif; ?>
