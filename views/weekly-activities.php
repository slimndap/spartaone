<?php
$athleteColumns = $athleteColumns ?? [];
$weekDays = $weekDays ?? [];
$activityCharts = $activityCharts ?? [];
$weekRangeLabel = $weekRangeLabel ?? '';
$hasActivities = !empty($hasActivities);
$activitiesDir = $activitiesDir ?? null;
?>

<div class="glass-card card shadow-lg mb-3">
    <div class="card-body">
        <style>
            .weekly-activities-table {
                border-collapse: collapse;
                width: 100%;
            }
            .weekly-activities-table th,
            .weekly-activities-table td {
                border: 1px solid rgba(255, 255, 255, 0.15);
            }
            .weekly-activities-table thead th {
                border-bottom-width: 2px;
            }
        </style>
        <div class="d-flex flex-column flex-md-row align-items-start gap-3 mb-3">
            <div>
                <div class="section-label mb-1">Activiteiten</div>
                <h2 class="h5 mb-0 text-light">Matrix van de afgelopen 7 dagen</h2>
            </div>
            <?php if ($weekRangeLabel): ?>
                <div class="text-secondary small ms-md-auto"><?php echo htmlspecialchars($weekRangeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </div>

        <?php if (!$activitiesDir): ?>
            <div class="alert alert-warning border-0 mb-0">Activiteiten zijn nog niet gesynchroniseerd. De map <code>data/activities</code> moet bestaan.</div>
        <?php elseif (!$athleteColumns): ?>
            <div class="text-secondary">Nog geen sporters geregistreerd. Zodra teamleden inloggen verschijnen ze hier.</div>
        <?php elseif (!$hasActivities): ?>
            <div class="text-secondary">Er zijn geen activiteiten in de afgelopen 7 dagen.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-borderless text-light align-middle mb-0 bg-transparent weekly-activities-table" aria-label="Activiteitenmatrix">
                    <thead>
                        <tr>
                            <th class="text-secondary small" scope="col">Dag</th>
                            <?php foreach ($athleteColumns as $column): ?>
                                <th class="text-start text-light small bg-transparent" scope="col" style="width: 220px; min-width: 220px;">
                                    <?php echo htmlspecialchars($column['name'] ?? 'Sporters', ENT_QUOTES, 'UTF-8'); ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weekDays as $day): ?>
                            <tr>
                                <th class="text-secondary small align-middle bg-transparent" scope="row">
                                    <div><?php echo htmlspecialchars($day['label'] ?? $day['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($day['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php
                                        $trainingEntries = $day['trainingSchedule'] ?? [];
                                        $trainingLabels = [];
                                        foreach ((array)$trainingEntries as $entry) {
                                            if (!is_array($entry)) {
                                                continue;
                                            }
                                            $entryTitle = trim((string)($entry['title'] ?? ''));
                                            $entryActivity = trim((string)($entry['activity'] ?? ''));
                                            $entryDistance = trim((string)($entry['distance'] ?? ''));
                                            $labelParts = [];
                                            if ($entryTitle !== '') {
                                                $labelParts[] = $entryTitle;
                                            } elseif ($entryActivity !== '') {
                                                $labelParts[] = $entryActivity;
                                            }
                                            if ($entryDistance !== '') {
                                                $labelParts[] = $entryDistance;
                                            }
                                            if ($labelParts) {
                                                $trainingLabels[] = implode(' · ', $labelParts);
                                            }
                                        }
                                    ?>
                                    <?php if ($trainingLabels): ?>
                                        <div class="small mt-1">
                                            <strong>Schema:</strong>
                                            <?php echo htmlspecialchars(implode(' / ', $trainingLabels), ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                </th>
                                <?php foreach ($athleteColumns as $column): 
                                    $cellActivities = $day['athleteActivities'][$column['id']] ?? [];
                                ?>
                                    <td class="align-top bg-transparent" style="width: 220px; min-width: 220px;">
                                        <?php if (!$cellActivities): ?>
                                            <div class="text-secondary small">Geen activiteiten</div>
                                        <?php else: ?>
                                            <div class="d-flex flex-column gap-3">
                                                <?php foreach ($cellActivities as $cellActivity): 
                                                    $activity = $cellActivity['activity'];
                                                    $activityName = trim((string)($activity['name'] ?? ''));
                                                    $activityType = trim((string)($activity['sport_type'] ?? $activity['type'] ?? ''));
                                                    $distanceKm = null;
                                                    if (isset($activity['distance']) && is_numeric($activity['distance'])) {
                                                        $distanceKm = (float)$activity['distance'] / 1000;
                                                    }
                                                    $timeLabel = '';
                                                    $dateRaw = $activity['start_date_local'] ?? $activity['start_date'] ?? '';
                                                    if ($dateRaw) {
                                                        try {
                                                            $dateObj = new DateTimeImmutable($dateRaw);
                                                            $timeLabel = $dateObj->format('H:i');
                                                        } catch (Exception $e) {
                                                            $timeLabel = '';
                                                        }
                                                    }
                                                ?>
                                                    <div class="bg-transparent text-light">
                                                        <div class="d-flex align-items-start justify-content-between gap-2">
                                                            <div class="fw-semibold">
                                                                <?php if (!empty($activity['id'])): ?>
                                                                    <a class="text-decoration-none text-light" target="_blank" rel="noopener noreferrer" href="https://www.strava.com/activities/<?php echo urlencode((string)$activity['id']); ?>">
                                                                        <?php echo htmlspecialchars($activityName ?: 'Activiteit', ENT_QUOTES, 'UTF-8'); ?>
                                                                    </a>
                                                                <?php else: ?>
                                                                    <?php echo htmlspecialchars($activityName ?: 'Activiteit', ENT_QUOTES, 'UTF-8'); ?>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if ($timeLabel): ?>
                                                                <div class="text-secondary small"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-secondary small">
                                                            <?php echo htmlspecialchars($activityType ?: 'Onbekend', ENT_QUOTES, 'UTF-8'); ?>
                                                            <?php if ($distanceKm !== null): ?>
                                                                · <?php echo htmlspecialchars(number_format($distanceKm, 1, '.', ''), ENT_QUOTES, 'UTF-8'); ?> km
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($cellActivity['hasChart']): ?>
                                                            <div class="mt-2 w-100" style="height: 120px;">
                                                                <canvas id="<?php echo htmlspecialchars($cellActivity['chartId'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Tempo zones per lap" role="img"></canvas>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php render_partial('cards/activity_charts', ['activityCharts' => $activityCharts]); ?>
