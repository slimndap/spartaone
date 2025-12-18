<?php
// Expected variables:
// - array $goal        Goal data with description/target_date/optional id.
// - array $goalOptions Optional rendering options.

$goal = $goal ?? [];
$goalOptions = $goalOptions ?? [];

$description = trim((string)($goal['description'] ?? ''));
if ($description === '') {
    return;
}

$allowPast = (bool)($goalOptions['allowPast'] ?? false);
$actionsHtml = (string)($goalOptions['actionsHtml'] ?? '');
$extraClass = trim((string)($goalOptions['class'] ?? ''));
$statusLabel = $goalOptions['statusLabel'] ?? null;

$targetDateStr = trim((string)($goal['target_date'] ?? ''));
$today = new DateTimeImmutable('today');
$dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $targetDateStr) ?: false;
$isValidDate = $dateObj && $dateObj->format('Y-m-d') === $targetDateStr;

if (!$allowPast) {
    if (!$isValidDate || $dateObj < $today) {
        return;
    }
}

$computedStatus = null;
$dateLabel = 'Geen datum';
$daysLabel = 'N.t.b.';
if ($isValidDate) {
    $weekdayMap = [
        'Mon' => 'Ma',
        'Tue' => 'Di',
        'Wed' => 'Wo',
        'Thu' => 'Do',
        'Fri' => 'Vr',
        'Sat' => 'Za',
        'Sun' => 'Zo',
    ];
    $dateLabel = str_replace(array_keys($weekdayMap), array_values($weekdayMap), $dateObj->format('D d-m-Y'));
    $daysLeft = (int)$today->diff($dateObj)->format('%r%a');
    if ($daysLeft === 0) {
        $daysLabel = 'Vandaag';
    } elseif ($daysLeft === 1) {
        $daysLabel = 'Morgen';
    } elseif ($daysLeft > 1) {
        $daysLabel = $daysLeft . ' dagen';
    } else {
        $computedStatus = 'Verlopen';
        $daysLabel = '0 dagen';
    }
} else {
    $computedStatus = 'Datum ontbreekt';
}

if ($statusLabel === null) {
    $statusLabel = $computedStatus;
}

?>
<div class="glass-card card shadow-lg mb-3 <?php echo htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="card-body goal-card__body">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
            <div class="goal-days-pill text-uppercase small">
                <span class="text-secondary">Nog</span>
                <span class="fw-semibold text-light"><?php echo htmlspecialchars($daysLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="section-label mb-1">Doel</div>
                <div class="h5 mb-1 text-light text-truncate"><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="goal-meta d-flex align-items-center gap-3 flex-wrap text-secondary small">
                    <?php if ($isValidDate): ?>
                        <span class="d-inline-flex align-items-center gap-2">
                            <i class="bi bi-calendar-event text-brand-accent"></i>
                            <span><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if ($statusLabel): ?>
                        <span class="badge rounded-pill text-bg-warning text-dark"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($actionsHtml): ?>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <?php echo $actionsHtml; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
