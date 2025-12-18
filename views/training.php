<?php
$trainingsByDate = [];
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

$monthParam = isset($_GET['month']) && preg_match('/^\\d{4}-\\d{2}$/', $_GET['month']) ? $_GET['month'] : null;
$monthStart = $monthParam ? DateTimeImmutable::createFromFormat('Y-m-d', $monthParam . '-01') : new DateTimeImmutable('first day of this month');
if (!$monthStart) {
    $monthStart = new DateTimeImmutable('first day of this month');
}
$monthLabel = $monthStart->format('F Y');
$prevMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$isCurrentMonth = $monthStart->format('Y-m') === (new DateTimeImmutable('first day of this month'))->format('Y-m');
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
        <div>
            <?php
            $sortedDates = array_keys($trainingsByDate);
            sort($sortedDates);
$todayStr = (new DateTimeImmutable('today'))->format('Y-m-d');
foreach ($sortedDates as $dateKey):
    if ($dateKey < $todayStr) {
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
                        'month_param' => $monthStart->format('Y-m'),
                    ],
                ]);
            ?>
        </div>
        <?php if (!empty($isAdmin) && $editDate === $dateKey && isset($entries[$editIdx])): ?>
            <?php $entry = $entries[$editIdx]; ?>
            <form method="post" action="?action=training&month=<?php echo htmlspecialchars($monthStart->format('Y-m'), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-2 mt-2">
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
                <div class="d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary btn-sm" href="?action=training&month=<?php echo htmlspecialchars($monthStart->format('Y-m'), ENT_QUOTES, 'UTF-8'); ?>">Annuleer</a>
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
