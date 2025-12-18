<?php
// Expected variables:
// - array $pace with keys 'title' and 'value'
$pace = $pace ?? [];
$paceTitle = trim((string)($pace['title'] ?? ''));
$paceValue = trim((string)($pace['value'] ?? ''));

if ($paceValue === '') {
    return;
}

if ($paceTitle === '') {
    $paceTitle = 'Tempo';
}
?>
<div class="glass-card card shadow-lg mb-3">
    <div class="card-body">
        <div class="section-label mb-1"><?php echo htmlspecialchars($paceTitle, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-speedometer2 text-brand-accent fs-4"></i>
                <div>
                    <div class="fw-semibold text-light"><?php echo htmlspecialchars($paceValue, ENT_QUOTES, 'UTF-8'); ?> min/km</div>
                </div>
            </div>
        </div>
    </div>
</div>
