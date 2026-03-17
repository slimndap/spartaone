<?php
// Render optional message banner.
?>
<?php if (!empty($message)): ?>
    <div class="alert alert-warning border-0 mb-3">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>
<div class="home-hero glass-card card shadow-lg">
    <div class="card-body">
        <?php if (!$tokens): ?>
            <p class="home-login-text mb-3">Log in met Strava om het actuele trainingschema van SpartaOne te bekijken.</p>
            <a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="d-inline-block">
                <img src="assets/img/strava/btn_strava_connect_with_orange_x2.svg" alt="Connect with Strava" class="home-strava-img">
            </a>
        <?php else: ?>
            <?php
            $entry = (!empty($nextTraining['entries']) && is_array($nextTraining['entries'])) ? $nextTraining['entries'][0] : null;
            ?>
            <?php if ($athleteError): ?>
                <div class="alert alert-danger mb-3"><?php echo $athleteError; ?></div>
            <?php endif; ?>
            <?php if (!empty($nextTraining) && $entry): ?>
                <div class="home-next">
                    <?php render_partial('cards/training_day', [
                        'trainingDay' => $nextTraining,
                        'athleteRows' => $athleteRows ?? [],
                    ]); ?>
                </div>
            <?php else: ?>
                <p class="text-muted">Geen komende training gevonden.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php if ($tokens): ?>
    <div class="mt-3">
        <?php $homeOverallRanking = (isset($homeOverallRanking) && is_array($homeOverallRanking)) ? $homeOverallRanking : []; ?>
        <?php if ($homeOverallRanking): ?>
            <div class="glass-card card shadow-lg mb-3 rankings-summary-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <div class="section-label mb-1">Overall Ranking</div>
                            <div class="fw-semibold">Top 3 op Age Grade %</div>
                        </div>
                        <a href="?action=rankings" class="btn btn-sm btn-outline-secondary">Bekijk alles</a>
                    </div>
                    <div class="table-responsive training-results-table-wrapper rankings-table-bleed">
                        <table class="table table-borderless text-light align-middle mb-0 bg-transparent training-results-table training-results-table--overall">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Sporter</th>
                                    <th scope="col" class="text-end">Resultaten</th>
                                    <th scope="col" class="text-end">Age Grade %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($homeOverallRanking as $index => $rankingRow): ?>
                                    <?php
                                    $position = (int)($index + 1);
                                    $positionLabel = (string)$position;
                                    if ($position === 1) {
                                        $positionLabel = '🥇';
                                    } elseif ($position === 2) {
                                        $positionLabel = '🥈';
                                    } elseif ($position === 3) {
                                        $positionLabel = '🥉';
                                    }
                                    ?>
                                    <tr>
                                        <td data-label="#"><?php echo htmlspecialchars($positionLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Sporter"><?php echo htmlspecialchars((string)($rankingRow['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td data-label="Resultaten" class="text-end fw-semibold"><?php echo (int)($rankingRow['result_count'] ?? 0); ?></td>
                                        <td data-label="Age Grade %" class="text-end"><?php echo htmlspecialchars(sparta_format_age_grade_percent($rankingRow['score'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php render_partial('cards/recent_activities', [
            'activities' => $activities ?? [],
            'activitiesUpdatedAt' => $activitiesUpdatedAt ?? null,
            'tempoPaces' => $tempoPaces ?? [],
            'trainings' => $trainings ?? [],
            'activityLimit' => 1,
        ]); ?>
    </div>
<?php endif; ?>
<?php if (!empty($upcomingGoals)): ?>
    <div class="mt-4">
        <?php foreach ($upcomingGoals as $goal): ?>
            <?php render_partial('cards/goal', ['goal' => $goal, 'goalOptions' => []]); ?>
        <?php endforeach; ?>
    </div>
<?php elseif ( $tokens ): ?>
    <div class="glass-card card shadow-lg mt-3">
        <div class="card-body">
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                <p class="mb-0 text-muted">Stel je eerste doel in.</p>
                <a href="?action=goals" class="btn btn-primary">Naar doelen</a>
            </div>
        </div>
    </div>
<?php endif; ?>
