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
                            if ($dateRaw) {
                                try {
                                    $dateObj = new DateTimeImmutable($dateRaw);
                                    $dateLabel = $dateObj->format('d-m-Y');
                                } catch (Exception $e) {
                                    $dateLabel = '';
                                }
                            }
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
                            <?php if ($lapCount > 0): ?>
                                    <?php
                                        $bars = [];
                                        $maxDistance = 0.0;
                                        $opacityMap = [
                                            '3K' => 1.0,
                                            '5K' => 0.8,
                                            '10K' => 0.6,
                                            'Half Marathon' => 0.4,
                                            'Marathon' => 0.2,
                                            'Aeroob' => 0.1,
                                            'Aerobe' => 0.1,
                                            'Recovery' => 0.1,
                                        ];
                                        $defaultZoneOrder = ['3K', '5K', '10K', 'Half Marathon', 'Marathon', 'Aeroob', 'Recovery'];
                                        $zoneLabels = array_filter(array_keys($tempoPaces ?? []), static fn($label) => $label !== 'Aerobe');
                                        if ($zoneLabels === []) {
                                            $zoneLabels = $defaultZoneOrder;
                                        } else {
                                            $zoneLabels = array_values(array_unique(array_merge($zoneLabels, $defaultZoneOrder)));
                                        }
                                        $zoneCount = count($zoneLabels);
                                        $zoneIndexMap = array_flip($zoneLabels);
                                        foreach ($laps as $lapIndex => $lap) {
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
                                            if ($lapDistance === null || $lapSeconds === null || $lapDistance <= 0 || $lapSeconds <= 0) {
                                                continue;
                                            }
                                            $lapSpeed = ($lapDistance * 3600) / $lapSeconds;
                                            $lapPaceSeconds = (int)round($lapSeconds / $lapDistance);
                                            $tempoZone = sparta_find_tempo_zone($tempoPaces ?? [], $lapPaceSeconds);
                                            $lapLabel = $lapName !== '' ? $lapName : 'Lap ' . ($lapIndex + 1);
                                            $bars[] = [
                                                'label' => $lapLabel,
                                                'distance' => $lapDistance,
                                                'pace' => format_pace_seconds($lapPaceSeconds),
                                                'duration' => sparta_format_duration($lapSeconds),
                                                'zone' => $tempoZone,
                                                'zone_index' => $tempoZone !== null && isset($zoneIndexMap[$tempoZone]) ? $zoneIndexMap[$tempoZone] : null,
                                                'opacity' => $tempoZone && isset($opacityMap[$tempoZone]) ? $opacityMap[$tempoZone] : 0.7,
                                            ];
                                            $maxDistance = max($maxDistance, $lapDistance);
                                        }
                                    ?>
                                    <?php if ($bars): ?>
                                        <?php
                                            $hasUnknownZone = false;
                                            foreach ($bars as $bar) {
                                                if ($bar['zone_index'] === null) {
                                                    $hasUnknownZone = true;
                                                    break;
                                                }
                                            }
                                            if ($hasUnknownZone && !in_array('Onbekend', $zoneLabels, true)) {
                                                $zoneLabels[] = 'Onbekend';
                                            }
                                            $zoneCount = count($zoneLabels);
                                            $zoneIndexMap = array_flip($zoneLabels);
                                            foreach ($bars as $barIndex => $bar) {
                                                if ($bar['zone_index'] === null) {
                                                    $bars[$barIndex]['zone_index'] = $zoneIndexMap['Onbekend'] ?? ($zoneCount - 1);
                                                    $bars[$barIndex]['zone'] = $bar['zone'] ?: 'Onbekend';
                                                }
                                            }
                                            $zoneStackOrder = array_reverse($zoneLabels);
                                            $stackValues = [];
                                            foreach ($zoneStackOrder as $zoneLabel) {
                                                $stackValues[$zoneLabel] = [];
                                            }
                                            foreach ($bars as $bar) {
                                                $zoneIndex = $bar['zone_index'] ?? ($zoneCount - 1);
                                                $slowIndex = ($zoneCount - 1) - $zoneIndex;
                                                foreach ($zoneStackOrder as $stackIndex => $zoneLabel) {
                                                    $stackValues[$zoneLabel][] = $stackIndex <= $slowIndex ? 1 : 0;
                                                }
                                            }
                                            $chartId = 'activity-chart-' . ($activity['id'] ?? $activityIndex);
                                            $activityCharts[] = [
                                                'id' => $chartId,
                                                'labels' => array_values(array_map(static fn($bar) => $bar['label'], $bars)),
                                                'zoneLabels' => array_values($zoneLabels),
                                                'zoneStackOrder' => array_values($zoneStackOrder),
                                                'zoneStacks' => $stackValues,
                                                'opacities' => array_values(array_map(static fn($bar) => $bar['opacity'], $bars)),
                                                'distances' => array_values(array_map(static fn($bar) => $bar['distance'], $bars)),
                                                'durations' => array_values(array_map(static fn($bar) => $bar['duration'], $bars)),
                                                'paces' => array_values(array_map(static fn($bar) => $bar['pace'], $bars)),
                                                'zones' => array_values(array_map(static fn($bar) => $bar['zone'], $bars)),
                                            ];
                                        ?>
                                        <div class="mt-2 w-100" style="height: 200px;">
                                            <canvas id="<?php echo htmlspecialchars($chartId, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Tempo zones per lap" role="img"></canvas>
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
    <?php if (!empty($activityCharts)): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const charts = <?php echo json_encode($activityCharts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                const rootStyles = getComputedStyle(document.documentElement);
                const accent = rootStyles.getPropertyValue('--brand-accent').trim() || '#f3c623';
                const toRgba = (color, alpha) => {
                    if (color.startsWith('#')) {
                        const hex = color.replace('#', '');
                        const value = hex.length === 3
                            ? hex.split('').map((ch) => ch + ch).join('')
                            : hex;
                        const intVal = parseInt(value, 16);
                        const r = (intVal >> 16) & 255;
                        const g = (intVal >> 8) & 255;
                        const b = intVal & 255;
                        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
                    }
                    if (color.startsWith('rgb(')) {
                        return color.replace('rgb(', 'rgba(').replace(')', `, ${alpha})`);
                    }
                    if (color.startsWith('rgba(')) {
                        return color.replace(/rgba\(([^)]+)\)/, `rgba($1, ${alpha})`);
                    }
                    return color;
                };

                charts.forEach((chartData) => {
                    const canvas = document.getElementById(chartData.id);
                    if (!canvas) {
                        return;
                    }
                    const axisColor = getComputedStyle(canvas.parentElement).color;
                    const backgroundColors = chartData.opacities.map((opacity) => toRgba(accent, opacity));
                    const datasets = chartData.zoneStackOrder.map((zoneLabel, zoneIndex) => ({
                        label: zoneLabel,
                        data: chartData.zoneStacks[zoneLabel] || [],
                        backgroundColor: backgroundColors,
                        borderRadius: zoneIndex === chartData.zoneStackOrder.length - 1 ? 4 : 0,
                        borderSkipped: false,
                        stack: 'zones',
                    }));
                    new Chart(canvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: chartData.labels,
                            datasets,
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    filter: (item) => item.datasetIndex === chartData.zoneStackOrder.length - 1,
                                    callbacks: {
                                        title: (items) => items[0]?.label || '',
                                        label: (context) => {
                                            const index = context.dataIndex;
                                            const pieces = [
                                                chartData.distances[index]
                                                    ? `${chartData.distances[index].toFixed(2)} km`
                                                    : null,
                                                chartData.durations[index] || null,
                                                chartData.paces[index] ? `${chartData.paces[index]}/km` : null,
                                                chartData.zones[index] || null,
                                            ];
                                            return pieces.filter(Boolean).join(' · ');
                                        },
                                    },
                                },
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { color: axisColor },
                                    stacked: true,
                                },
                                y: {
                                    beginAtZero: true,
                                    suggestedMin: 0,
                                    suggestedMax: Math.max(chartData.zoneStackOrder.length, 1),
                                    stacked: true,
                                    ticks: {
                                        color: axisColor,
                                        stepSize: 1,
                                        callback: (value) => {
                                            if (!Number.isInteger(value) || value <= 0) {
                                                return '';
                                            }
                                            return chartData.zoneStackOrder[value - 1] ?? '';
                                        },
                                    },
                                },
                            },
                        },
                    });
                });
            });
        </script>
    <?php endif; ?>
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
