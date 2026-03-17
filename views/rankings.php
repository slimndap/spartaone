<?php
$rankingDays = (isset($rankingDays) && is_array($rankingDays)) ? $rankingDays : [];
$athleteRows = (isset($athleteRows) && is_array($athleteRows)) ? $athleteRows : [];
$overallRanking = (isset($overallRanking) && is_array($overallRanking)) ? $overallRanking : [];
$rankingWindows = (isset($rankingWindows) && is_array($rankingWindows)) ? array_values($rankingWindows) : [];
?>

<div class="training-shell">
    <div class="card shadow-sm mb-4 glass-card training-card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                <div>
                    <div class="section-label mb-1">Overall Ranking</div>
                    <div class="fw-semibold">Gewogen score over de afgelopen 36 maanden</div>
                </div>
            </div>
            <?php if ($overallRanking === []): ?>
                <p class="text-muted mb-0">Nog geen age-graded resultaten in de afgelopen drie jaar.</p>
            <?php else: ?>
                <div class="table-responsive training-results-table-wrapper">
                    <table class="table table-borderless text-light align-middle mb-0 bg-transparent training-results-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Sporter</th>
                                <th scope="col" class="text-end">Resultaten</th>
                                <th scope="col" class="text-end">Age Grade %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overallRanking as $index => $rankingRow): ?>
                                <tr>
                                    <td><?php echo (int)($index + 1); ?></td>
                                    <td><?php echo htmlspecialchars((string)($rankingRow['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-end fw-semibold"><?php echo (int)($rankingRow['result_count'] ?? 0); ?></td>
                                    <td class="text-end"><?php echo htmlspecialchars(sparta_format_age_grade_percent($rankingRow['score'] ?? null), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="form-text mt-2">
                    Weging:
                    <?php foreach ($rankingWindows as $index => $window): ?>
                        <?php if ($index > 0): ?>, <?php endif; ?>
                        <?php echo htmlspecialchars((string)($window['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> =
                        <?php echo htmlspecialchars(number_format(((float)($window['weight'] ?? 0.0)) * 100, 0), ENT_QUOTES, 'UTF-8'); ?>%
                    <?php endforeach; ?>.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card shadow-sm mb-4 glass-card training-card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
                <div>
                    <div class="section-label mb-1">Rankings</div>
                    <div class="fw-semibold">Alle wedstrijduitslagen</div>
                </div>
            </div>
            <div>
                <?php if ($rankingDays === []): ?>
                    <p class="text-muted mb-0">Nog geen wedstrijduitslagen gevonden.</p>
                <?php else: ?>
                    <?php foreach ($rankingDays as $rankingDay): ?>
                        <div class="training-day">
                            <div class="training-list">
                                <?php render_partial('cards/training_day', [
                                    'trainingDay' => $rankingDay,
                                    'athleteRows' => $athleteRows,
                                ]); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
