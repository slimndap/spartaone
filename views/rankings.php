<?php
$rankingDays = (isset($rankingDays) && is_array($rankingDays)) ? $rankingDays : [];
$athleteRows = (isset($athleteRows) && is_array($athleteRows)) ? $athleteRows : [];
?>

<div class="training-shell">
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
