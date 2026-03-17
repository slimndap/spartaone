<div class="glass-card card shadow-lg mb-3 p-3">

    <?php $sliderValue = $basePaceSeconds ?? 210; ?>
<?php
$levelLabel = null;
if (!empty($selectedPaceRow['label'])) {
    $parts = explode('(', $selectedPaceRow['label'], 2);
    $levelLabel = trim($parts[0]);
}
?>
    <form method="post" action="?action=tempos" class="tempo-slider mb-0">
        <div class="section-label mb-1">Jouw 10K tempo</div>
        <div class="tempo-current-value" id="pace_display"><?php echo htmlspecialchars(format_pace_seconds($sliderValue), ENT_QUOTES, 'UTF-8'); ?> <span class="tempo-unit">min/km</span></div>
        <?php if ($levelLabel): ?>
            <div class="tempo-level-label" id="pace_level"><?php echo htmlspecialchars($levelLabel, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php else: ?>
            <div class="tempo-level-label" id="pace_level"></div>
        <?php endif; ?>
        <input type="range" class="form-range tempo-range" id="base_pace_seconds" name="base_pace_seconds" min="180" max="330" step="1" value="<?php echo htmlspecialchars($sliderValue, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="d-flex justify-content-between small text-secondary mt-1">
            <span>03:00</span>
            <span>05:30</span>
        </div>
        <?php if (!empty($basePaceError)): ?>
            <div class="text-danger small mt-1"><?php echo htmlspecialchars($basePaceError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
    </form>
</div>

<div class="glass-card card shadow-lg mb-3 p-3">
    <form method="post" action="?action=tempos" class="mb-0">
        <div class="section-label mb-1">Profiel</div>
        <div class="fw-semibold mb-3">Persoonlijke gegevens</div>
        <?php if (!empty($profileMessage)): ?>
            <div class="alert alert-success mb-3"><?php echo htmlspecialchars($profileMessage, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if (!empty($profileError)): ?>
            <div class="alert alert-danger mb-3"><?php echo htmlspecialchars($profileError, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <input type="hidden" name="save_profile" value="1">
        <div class="mb-3">
            <label for="birthdate" class="form-label">Geboortedatum</label>
            <input
                type="date"
                class="form-control"
                id="birthdate"
                name="birthdate"
                value="<?php echo htmlspecialchars((string)($birthdate ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
            >
        </div>
        <div class="mb-3">
            <label for="gender" class="form-label">Gender</label>
            <select class="form-select" id="gender" name="gender">
                <option value="">Niet ingesteld</option>
                <?php foreach (($genderOptions ?? []) as $genderValue => $genderLabel): ?>
                    <option value="<?php echo htmlspecialchars($genderValue, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($gender ?? '') === $genderValue) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($genderLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Opslaan</button>
        </div>
    </form>
</div>

<?php if (!empty($selectedPaceRow) && !empty($basePaceSeconds)): ?>
    <?php
    $paceCards = [
        ['label' => '3K tempo', 'value' => $selectedPaceRow['three_k'] ?? '', 'value_class' => 'pace-3k'],
        ['label' => '5K tempo', 'value' => $selectedPaceRow['five_k'] ?? '', 'value_class' => 'pace-5k'],
        ['label' => '10K tempo', 'value' => $selectedPaceRow['ten_k'] ?? '', 'value_class' => 'pace-10k'],
        ['label' => 'Halve marathon tempo', 'value' => $selectedPaceRow['half_marathon'] ?? '', 'value_class' => 'pace-hm'],
        ['label' => 'Marathon tempo', 'value' => $selectedPaceRow['marathon'] ?? '', 'value_class' => 'pace-m'],
        ['label' => 'Aeroob-range', 'value' => $selectedPaceRow['aerobe'] ?? '', 'value_class' => 'pace-aerobe'],
    ];
    foreach ($paceCards as $card) {
        if (trim((string)$card['value']) === '') {
            continue;
        }
        render_partial('cards/pace', [
            'pace' => [
                'title' => $card['label'],
                'value' => $card['value'],
                'value_class' => $card['value_class'],
            ],
        ]);
    }
    ?>
<?php endif; ?>

<script>
function formatPace(val) {
    const v = Math.max(0, parseInt(val, 10) || 0);
    const m = Math.floor(v / 60);
    const s = v % 60;
    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}

const slider = document.getElementById('base_pace_seconds');
const paceDisplay = document.getElementById('pace_display');
const paceLevel = document.getElementById('pace_level');
const tempoTable = <?php echo json_encode($paceTable, JSON_UNESCAPED_UNICODE); ?>;
const levelEls = document.querySelectorAll('.level-label');
const pace3Els = document.querySelectorAll('.pace-3k');
const pace5Els = document.querySelectorAll('.pace-5k');
const pace10Els = document.querySelectorAll('.pace-10k');
const paceHmEls = document.querySelectorAll('.pace-hm');
const paceMarathonEls = document.querySelectorAll('.pace-m');
const paceAerobeEls = document.querySelectorAll('.pace-aerobe');

let saveTimer = null;
function autoSave(newBase) {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
        const formData = new FormData();
        formData.append('base_pace_seconds', String(newBase));
        fetch('?action=tempos', { method: 'POST', body: formData, credentials: 'same-origin' });
    }, 300);
}

if (slider && paceDisplay) {
    slider.addEventListener('input', (e) => {
        const val = parseInt(e.target.value, 10) || 0;
        paceDisplay.innerHTML = `${formatPace(val)} <span class="tempo-unit">min/km</span>`;
        // pick nearest row
        let best = tempoTable[0];
        let bestDiff = Math.abs((best.base || 0) - val);
        tempoTable.forEach((row) => {
            const diff = Math.abs((row.base || 0) - val);
            if (diff < bestDiff) {
                best = row;
                bestDiff = diff;
            }
        });
        if (best) {
            if (paceLevel) {
                const parts = (best.label || '').split('(');
                paceLevel.textContent = (parts[0] || '').trim();
            }
            levelEls.forEach((el) => (el.textContent = best.label || ''));
            pace3Els.forEach((el) => (el.textContent = best.three_k || ''));
            pace5Els.forEach((el) => (el.textContent = best.five_k || ''));
            pace10Els.forEach((el) => (el.textContent = best.ten_k || ''));
            paceHmEls.forEach((el) => (el.textContent = best.half_marathon || ''));
            paceMarathonEls.forEach((el) => (el.textContent = best.marathon || ''));
            paceAerobeEls.forEach((el) => (el.textContent = best.aerobe || ''));
        }
        autoSave(val);
    });
}
</script>
