<?php
// Expected variables:
// - array $trainingDay with keys date, entries, view_state (optional)
// - array $athleteRows (optional)

$trainingDay = $trainingDay ?? [];
$entries = $trainingDay['entries'] ?? [];
if (!$entries || !is_array($entries)) {
    return;
}

$athleteRows = $athleteRows ?? [];
$dateStr = $trainingDay['date'] ?? null;
$isAdmin = function_exists('user_is_admin') ? user_is_admin() : false;
$tempoPaces = function_exists('get_tempo_paces_context') ? get_tempo_paces_context() : [];
$viewState = $trainingDay['view_state'] ?? null;
$raceOptions = function_exists('sparta_training_race_options') ? sparta_training_race_options() : [];
$athleteMetaMap = [];
foreach ($athleteRows as $athleteRow) {
    $athleteId = (string)($athleteRow['id'] ?? '');
    if ($athleteId === '') {
        continue;
    }
    $athleteMetaMap[$athleteId] = [
        'name' => (string)($athleteRow['name'] ?? $athleteId),
        'gender' => (string)($athleteRow['gender'] ?? ''),
        'age' => $athleteRow['age'] ?? null,
    ];
}

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
    $raceType = function_exists('sparta_normalize_training_race_type')
        ? sparta_normalize_training_race_type($entry['race_type'] ?? '')
        : '';
    $raceLabel = $raceType !== '' ? ($raceOptions[$raceType] ?? 'Wedstrijd') : '';
    $finishTimes = function_exists('sparta_normalize_training_finish_times')
        ? sparta_normalize_training_finish_times($entry['finish_times'] ?? [])
        : [];
    if ($finishTimes) {
        uasort($finishTimes, static function ($left, $right): int {
            $leftTs = strtotime('1970-01-01 ' . $left . ' UTC');
            $rightTs = strtotime('1970-01-01 ' . $right . ' UTC');
            if ($leftTs !== false && $rightTs !== false) {
                return $leftTs <=> $rightTs;
            }
            return strnatcasecmp((string)$left, (string)$right);
        });
    }

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
        $derivedState = 'upcoming';
        try {
            $today = new DateTimeImmutable('today');
            $targetDate = new DateTimeImmutable($dateStr);
            if ($targetDate < $today) {
                $derivedState = 'past';
            }
        } catch (Exception $e) {
            $derivedState = 'upcoming';
        }
        $editUrl = '?action=training'
            . '&state=' . rawurlencode($viewState ?: $derivedState)
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
            <?php if ($raceLabel !== ''): ?>
                <span class="home-pill">
                    <i class="bi bi-flag-fill me-1"></i><?php echo htmlspecialchars($raceLabel, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            <?php endif; ?>
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
            <?php foreach ($tempos as $tempo):
                $tempoLabel = $tempo === 'Aerobe' ? 'Aeroob' : $tempo;
            ?>
                <span class="home-pill">
                    <?php echo htmlspecialchars($tempoLabel, ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($tempoPaces[$tempo])): ?>
                        <span class="text-muted small ms-1"><?php echo htmlspecialchars($tempoPaces[$tempo], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php if ($notes): ?>
            <div class="home-notes"><?php echo htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($finishTimes): ?>
            <div class="mt-2">
                <div class="home-block-label">Finishtijden</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Sporter</th>
                                <th scope="col">Gender</th>
                                <th scope="col">Leeftijd</th>
                                <th scope="col" class="text-end">Tijd</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($finishTimes as $athleteId => $finishTime):
                                $athleteMeta = $athleteMetaMap[$athleteId] ?? ['name' => $athleteId, 'gender' => '', 'age' => null];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($athleteMeta['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($athleteMeta['gender'] !== '' ? $athleteMeta['gender'] : '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $athleteMeta['age'] !== null ? (int)$athleteMeta['age'] : '-'; ?></td>
                                    <td class="text-end fw-semibold"><?php echo htmlspecialchars($finishTime, ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
