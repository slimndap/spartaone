<?php
// Expected variables:
// - array $trainingDay with keys date, entries, month_param (optional)

$trainingDay = $trainingDay ?? [];
$entries = $trainingDay['entries'] ?? [];
if (!$entries || !is_array($entries)) {
    return;
}

$dateStr = $trainingDay['date'] ?? null;
$isAdmin = function_exists('user_is_admin') ? user_is_admin() : false;
$tempoPaces = function_exists('get_tempo_paces_context') ? get_tempo_paces_context() : [];
$monthParam = $trainingDay['month_param'] ?? null;

$heading = null;
if ($dateStr) {
    try {
        $today = new DateTimeImmutable('today');
        $target = new DateTimeImmutable($dateStr);
        $formatted = $target->format('D d-m-Y');
        $formatted = str_replace(
            ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'],
            $formatted
        );
        if ($target->format('Y-m-d') === $today->format('Y-m-d')) {
            $heading = 'Training van vandaag';
        } else {
            $tomorrow = $today->modify('+1 day')->format('Y-m-d');
            $dayAfter = $today->modify('+2 day')->format('Y-m-d');
            if ($target->format('Y-m-d') === $tomorrow) {
                $heading = 'Training van morgen';
            } elseif ($target->format('Y-m-d') === $dayAfter) {
                $heading = 'Training van overmorgen';
            } else {
                $heading = 'Training op ' . $formatted;
            }
        }
    } catch (Exception $e) {
        $heading = null;
    }
}

foreach ($entries as $idx => $entry) {
    $title = $entry['title'] ?? '';
    $activity = $entry['activity'] ?? '';
    $distance = $entry['distance'] ?? '';
    $tempos = (!empty($entry['tempos']) && is_array($entry['tempos'])) ? $entry['tempos'] : [];
    $notes = $entry['notes'] ?? '';

    $location = $entry['location'] ?? null;
    if (!$location && $dateStr) {
        try {
            $weekday = (int)(new DateTimeImmutable($dateStr))->format('N');
            if (in_array($weekday, [1, 3], true)) {
                $location = 'AV Sparta (Voorburg), Groene Zoom 20, 2491 EH The Hague, Netherlands';
            }
        } catch (Exception $e) {
            $location = null;
        }
    }

    $entryHeading = $heading && $idx === 0 ? $heading : null;
    $editUrl = $entry['edit_url'] ?? null;
    if ($isAdmin && $dateStr && $editUrl === null) {
        try {
            $editMonth = (new DateTimeImmutable($dateStr))->format('Y-m');
        } catch (Exception $e) {
            $editMonth = null;
        }
        $editUrl = '?action=training'
            . ($monthParam ? '&month=' . rawurlencode($monthParam) : ($editMonth ? '&month=' . rawurlencode($editMonth) : ''))
            . '&edit_date=' . rawurlencode($dateStr)
            . '&edit_idx=' . $idx;
    }
    ?>
    <div class="training-block">
        <?php if ($entryHeading): ?>
            <div class="home-block-label"><?php echo htmlspecialchars($entryHeading, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($title): ?>
            <div class="home-next-title mb-1"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <div class="home-meta">
            <?php if ($activity): ?>
                <span class="home-pill">
                    <i class="bi bi-activity me-1"></i><?php echo htmlspecialchars($activity, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            <?php endif; ?>
            <?php if ($distance): ?>
                <span class="home-pill">
                    <i class="bi bi-arrow-right me-1"></i><?php echo htmlspecialchars($distance, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            <?php endif; ?>
            <?php foreach ($tempos as $tempo): ?>
                <span class="home-pill">
                    <?php echo htmlspecialchars($tempo, ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($tempoPaces[$tempo])): ?>
                        <span class="text-muted small ms-1"><?php echo htmlspecialchars($tempoPaces[$tempo], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php if ($notes): ?>
            <div class="home-notes"><?php echo htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($location): ?>
            <div class="home-location mt-2">
                <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <?php if ($editUrl): ?>
            <div class="text-end mt-2">
                <a class="text-decoration-none small" href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>">Bewerk</a>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
