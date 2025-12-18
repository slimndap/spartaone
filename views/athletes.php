<?php if (!empty($storageError)): ?>
    <div class="alert alert-danger bg-gradient border-0 text-dark"><?php echo htmlspecialchars($storageError, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if (empty($otherAthletes)): ?>
    <div class="card glass-card shadow-sm border-0 mb-3">
        <div class="card-body d-flex align-items-center gap-3">
            <i class="bi bi-people text-primary fs-3"></i>
            <div>
                <div class="fw-semibold text-light">Nog geen sporters</div>
                <p class="mb-0 text-secondary small">Zodra teamleden inloggen verschijnen ze hier met hun Strava-profiel.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php $today = new DateTimeImmutable('today'); ?>
    <div class="row gy-3">
        <?php foreach ($otherAthletes as $ath): 
            $fullName = trim(($ath['firstname'] ?? '') . ' ' . ($ath['lastname'] ?? ''));
            $initial = mb_substr($fullName, 0, 1);
            $isCurrent = !empty($currentAthleteId) && (string)$currentAthleteId === (string)($ath['id'] ?? '');
            $pace10k = $ath['pace_10k'] ?? null;
            $firstGoal = null;
            if (!empty($ath['goals']) && is_array($ath['goals'])) {
                $upcoming = sparta_next_goals($ath['goals'], 1);
                if (!empty($upcoming[0]['description'])) {
                    $firstGoal = trim((string)$upcoming[0]['description']);
                }
            }
        ?>
            <div class="col-12">
                <a class="text-decoration-none" href="?action=athlete&id=<?php echo urlencode($ath['id'] ?? ''); ?>">
                    <div class="glass-card card shadow-lg h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <div class="athlete-avatar">
                                <?php if (!empty($ath['profile'])): ?>
                                    <img src="<?php echo htmlspecialchars($ath['profile'], ENT_QUOTES, 'UTF-8'); ?>" alt="Profile">
                                <?php else: ?>
                                    <span class="fw-bold"><?php echo htmlspecialchars($initial ?: '?', ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="fw-semibold text-light"><?php echo htmlspecialchars($fullName ?: 'Onbekende sporter', ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($isCurrent): ?>
                                        <span class="badge text-bg-primary rounded-pill">Jij</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($firstGoal): ?>
                                    <div class="text-secondary small d-flex align-items-center gap-2">
                                        <i class="bi bi-bullseye"></i>
                                        <span><?php echo htmlspecialchars($firstGoal, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($pace10k): ?>
                                    <div class="text-secondary small d-flex align-items-center gap-2 mt-1">
                                        <i class="bi bi-speedometer2"></i>
                                        <span>10K: <?php echo htmlspecialchars($pace10k, ENT_QUOTES, 'UTF-8'); ?> min/km</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <i class="bi bi-chevron-right text-secondary"></i>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
