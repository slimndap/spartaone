<div class="glass-card card shadow-lg mb-3">
    <div class="card-body">
        <?php if (empty($athleteId)): ?>
            <div class="alert alert-warning mb-0">No athlete selected. Provide an ID via <code>?action=athlete&id=123</code>.</div>
        <?php elseif ($athlete): ?>
            <?php
                $fullName = trim(($athlete['firstname'] ?? '') . ' ' . ($athlete['lastname'] ?? ''));
                $initial = mb_substr($fullName, 0, 1);
                $pace10k = $athlete['pace_10k'] ?? null;
                $goalsList = [];
                if (!empty($athlete['goals']) && is_array($athlete['goals'])) {
                    $goalsList = sparta_next_goals($athlete['goals'], 0);
                }
            ?>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="athlete-avatar athlete-avatar-lg">
                        <?php if (!empty($athlete['profile'])): ?>
                            <img src="<?php echo htmlspecialchars($athlete['profile'], ENT_QUOTES, 'UTF-8'); ?>" alt="Profile image">
                        <?php else: ?>
                            <span class="fw-bold fs-4"><?php echo htmlspecialchars($initial ?: '?', ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="section-label mb-1">Sporter</div>
                        <h1 class="h4 mb-0 text-light"><?php echo htmlspecialchars($fullName ?: 'Onbekende sporter', ENT_QUOTES, 'UTF-8'); ?></h1>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (!empty($athlete['id'])): ?>
                        <a class="btn btn-outline-light btn-sm d-inline-flex align-items-center gap-1" target="_blank" rel="noopener noreferrer" href="https://www.strava.com/athletes/<?php echo urlencode($athlete['id']); ?>">
                            <i class="bi bi-box-arrow-up-right"></i>
                            <span>Bekijk op Strava</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">No athlete found for ID <strong><?php echo htmlspecialchars($athleteId, ENT_QUOTES, 'UTF-8'); ?></strong>.</div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($athlete)): ?>
    <?php
        if (!empty($pace10k)) {
            $paceData = [
                'title' => '10K tempo',
                'value' => $pace10k,
            ];
            render_partial('cards/pace', ['pace' => $paceData]);
        }
    ?>
    <div class="glass-card card shadow-lg mb-3">
        <div class="card-body">
            <div class="section-label mb-1">Recente activiteiten</div>
            <?php if (!empty($activities) && is_array($activities)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($activities as $activity): ?>
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
                            if ($dateRaw) {
                                try {
                                    $dateObj = new DateTimeImmutable($dateRaw);
                                    $dateLabel = $dateObj->format('d-m-Y');
                                } catch (Exception $e) {
                                    $dateLabel = '';
                                }
                            }
                        ?>
                        <div class="list-group-item bg-transparent text-light border-0 px-0 py-2 d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <div class="fw-semibold">
                                    <?php if (!empty($activity['id'])): ?>
                                        <a class="text-decoration-none text-light" target="_blank" rel="noopener noreferrer" href="https://www.strava.com/activities/<?php echo urlencode((string)$activity['id']); ?>">
                                            <?php echo htmlspecialchars($activityName ?: 'Activiteit', ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($activityName ?: 'Activiteit', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="text-secondary small">
                                    <?php echo htmlspecialchars($activityType ?: 'Onbekend', ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($distanceKm !== null): ?>
                                        · <?php echo htmlspecialchars(number_format($distanceKm, 1, '.', ''), ENT_QUOTES, 'UTF-8'); ?> km
                                    <?php endif; ?>
                                </div>
                                <?php if ($lapCount > 0): ?>
                                    <div class="text-secondary small mt-2">
                                        <?php foreach ($laps as $lapIndex => $lap): ?>
                                            <?php
                                                $lapName = trim((string)($lap['name'] ?? ''));
                                                $lapDistance = null;
                                                if (isset($lap['distance']) && is_numeric($lap['distance'])) {
                                                    $lapDistance = (float)$lap['distance'] / 1000;
                                                }
                                                $lapSeconds = null;
                                                if (isset($lap['elapsed_time']) && is_numeric($lap['elapsed_time'])) {
                                                    $lapSeconds = (int)$lap['elapsed_time'];
                                                } elseif (isset($lap['moving_time']) && is_numeric($lap['moving_time'])) {
                                                    $lapSeconds = (int)$lap['moving_time'];
                                                }
                                                $lapPaceSeconds = null;
                                                if ($lapSeconds !== null && $lapDistance !== null && $lapDistance > 0) {
                                                    $lapPaceSeconds = (int)round($lapSeconds / $lapDistance);
                                                }
                                                $lapPace = $lapPaceSeconds !== null ? format_pace_seconds($lapPaceSeconds) : '';
                                                $tempoZone = sparta_find_tempo_zone($tempoPaces ?? [], $lapPaceSeconds);
                                                $lapLabel = $lapName !== '' ? $lapName : 'Lap ' . ($lapIndex + 1);
                                                $lapDuration = sparta_format_duration($lapSeconds);
                                            ?>
                                            <div>
                                                <?php echo htmlspecialchars($lapLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if ($lapDistance !== null): ?>
                                                    · <?php echo htmlspecialchars(number_format($lapDistance, 1, '.', ''), ENT_QUOTES, 'UTF-8'); ?> km
                                                <?php endif; ?>
                                                <?php if ($lapDuration !== ''): ?>
                                                    · <?php echo htmlspecialchars($lapDuration, ENT_QUOTES, 'UTF-8'); ?>
                                                <?php endif; ?>
                                                <?php if ($lapPace !== ''): ?>
                                                    · <?php echo htmlspecialchars($lapPace, ENT_QUOTES, 'UTF-8'); ?>/km
                                                <?php endif; ?>
                                                <?php if ($tempoZone): ?>
                                                    · <?php echo htmlspecialchars($tempoZone, ENT_QUOTES, 'UTF-8'); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($dateLabel): ?>
                                <div class="text-secondary small"><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></div>
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
    <div class="mt-3">
        <?php if ($goalsList): ?>
            <?php foreach ($goalsList as $goalItem): ?>
                <?php
                    $goalData = is_array($goalItem) ? $goalItem : ['description' => (string)$goalItem];
                    render_partial('cards/goal', ['goal' => $goalData, 'goalOptions' => []]);
                ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
