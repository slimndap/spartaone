<?php
$goalsList = [];
if (!empty($goals) && is_array($goals)) {
    $goalsList = $goals;
} elseif (!empty($athlete['goals']) && is_array($athlete['goals'])) {
    $goalsList = $athlete['goals'];
} elseif (!empty($athletes) && !empty($currentAthleteId)) {
    foreach ($athletes as $ath) {
        if (($ath['id'] ?? null) == $currentAthleteId && !empty($ath['goals'])) {
            $goalsList = $ath['goals'];
            break;
        }
    }
}

$normalizedGoals = [];
foreach ($goalsList as $goal) {
    if (function_exists('normalize_goal_entry')) {
        $entry = normalize_goal_entry($goal);
    } else {
        $entry = is_array($goal) ? $goal : ['description' => (string)$goal];
    }
    if (empty($entry['description'])) {
        continue;
    }
    $normalizedGoals[] = $entry;
}

$today = new DateTimeImmutable('today');
$upcomingGoals = [];
$staleGoals = [];
foreach ($normalizedGoals as $goal) {
    $dateStr = $goal['target_date'] ?? '';
    $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $dateStr) ?: false;
    $validDate = $dateObj && $dateObj->format('Y-m-d') === $dateStr;
    if ($validDate && $dateObj >= $today) {
        $upcomingGoals[] = $goal;
    } else {
        $staleGoals[] = $goal;
    }
}

usort($upcomingGoals, static function ($a, $b) {
    return strcmp($a['target_date'] ?? '', $b['target_date'] ?? '');
});
?>

<?php if (!empty($goalError) || !empty($storageError)): ?>
    <div class="alert alert-danger bg-gradient border-0 text-dark">
        <?php echo htmlspecialchars($goalError ?? $storageError, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if (!empty($goalMessage) || !empty($saveGoalsSuccess)): ?>
    <div class="alert alert-success bg-gradient border-0 text-dark">
        <?php echo htmlspecialchars($goalMessage ?? $saveGoalsSuccess, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<?php if ($upcomingGoals): ?>
    <div class="section-label mb-2">Jouw actieve doelen</div>
    <?php foreach ($upcomingGoals as $goal): 
        $goalId = $goal['id'] ?? uniqid('goal_', true);
        $collapseId = 'edit-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $goalId);
        if ($collapseId === 'edit-') {
            $collapseId = 'edit-' . uniqid();
        }
        ob_start();
        ?>
        <button class="btn btn-sm btn-outline-light" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="false">
            Bewerk
        </button>
        <form method="post" class="d-inline">
            <input type="hidden" name="goal_id" value="<?php echo htmlspecialchars($goal['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="delete_goal" value="1">
            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Goal verwijderen?');">
                Verwijder
            </button>
        </form>
        <?php $actionsHtml = ob_get_clean(); ?>

        <?php sparta_render_goal($goal, ['actionsHtml' => $actionsHtml]); ?>
        <div class="collapse" id="<?php echo htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="glass-card card shadow-lg mb-3">
                <div class="card-body">
                    <div class="section-label mb-2">Bewerk doel</div>
                    <form method="post" class="goal-form row g-3">
                        <input type="hidden" name="update_goal" value="1">
                        <input type="hidden" name="goal_id" value="<?php echo htmlspecialchars($goal['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="col-12">
                            <label class="form-label text-secondary small text-uppercase">Omschrijving</label>
                            <input type="text" name="goal_description" class="form-control" required value="<?php echo htmlspecialchars($goal['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label text-secondary small text-uppercase">Doeldatum</label>
                            <input type="date" name="goal_target_date" class="form-control" required min="<?php echo htmlspecialchars($today->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($goal['target_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">Opslaan</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($collapseId, ENT_QUOTES, 'UTF-8'); ?>">Sluit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="glass-card card shadow-lg">
        <div class="card-body">
            <div class="section-label mb-1">Jouw actieve doelen</div>
            <p class="mb-0 text-secondary small">Nog geen doelen met een datum vanaf vandaag.</p>
        </div>
    </div>
<?php endif; ?>

<div class="glass-card card shadow-lg mt-4">
    <div class="card-body">
        <div class="section-label mb-1">Plan je volgende mijlpaal</div>
        <form method="post" class="goal-form row g-3">
            <input type="hidden" name="add_goal" value="1">
            <div class="col-12">
                <label class="form-label text-secondary small text-uppercase">Omschrijving</label>
                <input type="text" name="goal_description" class="form-control" placeholder="Bijvoorbeeld: 10 km onder de 45 minuten" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label text-secondary small text-uppercase">Doeldatum</label>
                <input type="date" name="goal_target_date" class="form-control" min="<?php echo htmlspecialchars($today->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
                <div class="form-text text-secondary">Alleen doelen met een datum vanaf vandaag worden getoond.</div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Doel opslaan
                </button>
            </div>
        </form>
    </div>
</div>
