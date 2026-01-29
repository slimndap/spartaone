<?php
$activities = (isset($activities) && is_array($activities)) ? $activities : [];
$activityLimit = isset($activityLimit) ? (int)$activityLimit : null;
if ($activityLimit && $activityLimit > 0) {
    $activities = array_slice($activities, 0, $activityLimit);
}
$tempoPaces = (isset($tempoPaces) && is_array($tempoPaces)) ? $tempoPaces : [];
$trainings = (isset($trainings) && is_array($trainings)) ? $trainings : [];
$trainingsByDate = [];
foreach ($trainings as $trainingDay) {
    $dateKey = $trainingDay['date'] ?? null;
    if (!$dateKey) {
        continue;
    }
    $entries = $trainingDay['entries'] ?? [];
    if (!is_array($entries) || $entries === []) {
        continue;
    }
    $trainingsByDate[$dateKey] = $entries;
}
?>
<div class="glass-card card shadow-lg mb-3">
    <div class="card-body">
        <div class="section-label mb-1">Recente activiteiten</div>
        <?php if (!empty($activities)): ?>
            <?php $activityCharts = []; ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($activities as $activityIndex => $activity): ?>
                    <?php
                        $activityName = trim((string)($activity['name'] ?? ''));
                        $activityType = trim((string)($activity['sport_type'] ?? $activity['type'] ?? ''));
                        $distanceKm = null;
                        if (isset($activity['distance']) && is_numeric($activity['distance'])) {
                            $distanceKm = (float)$activity['distance'] / 1000;
                        }
                        $laps = (isset($activity['laps']) && is_array($activity['laps'])) ? $activity['laps'] : [];
                        $lapCount = count($laps);
                        $dateLabel = '';
                        $dateRaw = $activity['start_date_local'] ?? $activity['start_date'] ?? '';
                        $activityDateKey = null;
                        if ($dateRaw) {
                            try {
                                $dateObj = new DateTimeImmutable($dateRaw);
                                $dateLabel = $dateObj->format('d-m-Y');
                                $activityDateKey = $dateObj->format('Y-m-d');
                            } catch (Exception $e) {
                                $dateLabel = '';
                            }
                        }
                        $trainingEntries = $activityDateKey && isset($trainingsByDate[$activityDateKey])
                            ? $trainingsByDate[$activityDateKey]
                            : [];
                    ?>
                    <div class="bg-transparent text-light">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <div class="fw-semibold">
                                <?php if (!empty($activity['id'])): ?>
                                    <a class="text-decoration-none text-light" target="_blank" rel="noopener noreferrer" href="https://www.strava.com/activities/<?php echo urlencode((string)$activity['id']); ?>">
                                        <?php echo htmlspecialchars($activityName ?: 'Activiteit', ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($activityName ?: 'Activiteit', ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($dateLabel): ?>
                                <div class="text-secondary small"><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-secondary small">
                            <?php echo htmlspecialchars($activityType ?: 'Onbekend', ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($distanceKm !== null): ?>
                                · <?php echo htmlspecialchars(number_format($distanceKm, 1, '.', ''), ENT_QUOTES, 'UTF-8'); ?> km
                            <?php endif; ?>
                        </div>
                        <?php
                            $chartId = 'activity-chart-' . ($activity['id'] ?? $activityIndex);
                            $chartData = sparta_build_activity_chart_data($activity, $tempoPaces ?? [], $chartId);
                            if ($chartData) {
                                $activityCharts[] = $chartData;
                            }
                        ?>
                        <?php if ($chartData): ?>
                            <div class="mt-2 w-100" style="height: 200px;">
                                <canvas id="<?php echo htmlspecialchars($chartId, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Tempo zones per lap" role="img"></canvas>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($trainingEntries)): ?>
                            <?php
                                $plannedParts = [];
                                foreach ($trainingEntries as $entry) {
                                    if (!is_array($entry)) {
                                        continue;
                                    }
                                    $entryTitle = trim((string)($entry['title'] ?? ''));
                                    $entryActivity = trim((string)($entry['activity'] ?? ''));
                                    $entryDistance = trim((string)($entry['distance'] ?? ''));
                                    $entryLabel = $entryTitle !== '' ? $entryTitle : ($entryActivity !== '' ? $entryActivity : 'Training');
                                    $plannedParts[] = $entryLabel;
                                }
                            ?>
                            <?php if ($plannedParts): ?>
                                <div class="text-secondary small mt-2">
                                    Gepland: <?php echo htmlspecialchars(implode(' | ', $plannedParts), ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($activitiesUpdatedAt)): ?>
                <div class="text-secondary small mt-2">
                    Laatste sync: <?php echo htmlspecialchars($activitiesUpdatedAt, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-secondary">Nog geen activiteiten gevonden.</div>
        <?php endif; ?>
    </div>
</div>
<?php render_partial('cards/activity_charts', ['activityCharts' => $activityCharts]); ?>
