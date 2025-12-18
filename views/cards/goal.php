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
$daysLeft = null;
if ($isValidDate) {

    $dateLabel = $dateObj->format('d-m-Y');
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

// Derive a simple urgency progress (time elapsed within a 90-day window).
$windowDays = 90;
$progressPercent = 100;
if ($daysLeft !== null) {
    if ($daysLeft > $windowDays) {
        $progressPercent = 10; // distant goals show minimal fill
    } elseif ($daysLeft <= 0) {
        $progressPercent = 100;
    } else {
        $progressPercent = max(10, min(100, (1 - ($daysLeft / $windowDays)) * 100));
    }
}

?>
<div class="glass-card card shadow-lg mb-3 <?php echo htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="card-body goal-card__body">
        <div class="d-flex flex-wrap align-items-center gap-3">

            <div class="flex-grow-1 min-w-0">
                <?php if ($isValidDate): ?>
                    <div class="section-label mb-1">Doel voor <?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif ?>
                <div class="h5 mb-1 text-light text-truncate"><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php if ($actionsHtml): ?>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    <?php echo $actionsHtml; ?>
                </div>
            <?php endif; ?>
            <div class="goal-progress w-100">
                <div class="goal-progress__bar">
                    <div class="goal-progress__fill" style="width: <?php echo (float)$progressPercent; ?>%;"></div>
                </div>
                <div class="goal-progress__label text-uppercase small">
                    <span class="text-secondary">Nog</span>
                    <span class="fw-semibold text-light"><?php echo htmlspecialchars($daysLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
