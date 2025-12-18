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

<?php if (!empty($selectedPaceRow) && !empty($basePaceSeconds)): ?>
    <div class="glass-card card shadow-lg p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
                <div class="section-label">Jouw trainingstempo's</div>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="tempo-chip">
                    <span class="text-secondary small">5K</span>
                    <span class="pace-5k fw-semibold text-light"><?php echo htmlspecialchars($selectedPaceRow['five_k'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="tempo-chip">
                    <span class="text-secondary small">10K</span>
                    <span class="pace-10k fw-semibold text-light"><?php echo htmlspecialchars($selectedPaceRow['ten_k'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="tempo-chip">
                    <span class="text-secondary small">HM</span>
                    <span class="pace-hm fw-semibold text-light"><?php echo htmlspecialchars($selectedPaceRow['half_marathon'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="tempo-chip">
                    <span class="text-secondary small">Marathon</span>
                    <span class="pace-m fw-semibold text-light"><?php echo htmlspecialchars($selectedPaceRow['marathon'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
            <div class="col-12 col-md-8 col-xl-4">
                <div class="tempo-chip">
                    <span class="text-secondary small">Aerobe-range</span>
                    <span class="pace-aerobe fw-semibold text-light"><?php echo htmlspecialchars($selectedPaceRow['aerobe'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>
        <p class="text-secondary small mt-2 mb-0">Tempo’s worden live bijgewerkt op basis van je slider keuze.</p>
    </div>
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
