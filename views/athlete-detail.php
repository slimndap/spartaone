<div class="glass-card card shadow-lg mb-3">
    <div class="card-body">
        <?php if (empty($athleteId)): ?>
            <div class="alert alert-warning mb-0">No athlete selected. Provide an ID via <code>?action=athlete&id=123</code>.</div>
        <?php elseif ($athlete): ?>
            <?php
                $fullName = trim(($athlete['firstname'] ?? '') . ' ' . ($athlete['lastname'] ?? ''));
                $initial = mb_substr($fullName, 0, 1);
                $pace10k = $athlete['pace_10k'] ?? null;
                $goalsList = [];
                if (!empty($athlete['goals']) && is_array($athlete['goals'])) {
                    $goalsList = sparta_next_goals($athlete['goals'], 0);
                }
            ?>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="athlete-avatar athlete-avatar-lg">
                        <?php if (!empty($athlete['profile'])): ?>
                            <img src="<?php echo htmlspecialchars($athlete['profile'], ENT_QUOTES, 'UTF-8'); ?>" alt="Profile image">
                        <?php else: ?>
                            <span class="fw-bold fs-4"><?php echo htmlspecialchars($initial ?: '?', ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="section-label mb-1">Sporter</div>
                        <h1 class="h4 mb-0 text-light"><?php echo htmlspecialchars($fullName ?: 'Onbekende sporter', ENT_QUOTES, 'UTF-8'); ?></h1>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (!empty($athlete['id'])): ?>
                        <a class="btn btn-outline-light btn-sm d-inline-flex align-items-center gap-1" target="_blank" rel="noopener noreferrer" href="https://www.strava.com/athletes/<?php echo urlencode($athlete['id']); ?>">
                            <i class="bi bi-box-arrow-up-right"></i>
                            <span>Bekijk op Strava</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">No athlete found for ID <strong><?php echo htmlspecialchars($athleteId, ENT_QUOTES, 'UTF-8'); ?></strong>.</div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($athlete)): ?>
    <?php
        if (!empty($pace10k)) {
            $paceData = [
                'title' => '10K tempo',
                'value' => $pace10k,
            ];
            render_partial('cards/pace', ['pace' => $paceData]);
        }
    ?>
    <div class="mt-3">
        <?php if ($goalsList): ?>
            <?php foreach ($goalsList as $goalItem): ?>
                <?php
                    $goalData = is_array($goalItem) ? $goalItem : ['description' => (string)$goalItem];
                    render_partial('cards/goal', ['goal' => $goalData, 'goalOptions' => []]);
                ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
