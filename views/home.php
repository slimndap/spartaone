<?php
// Render optional message banner.
?>
<?php if (!empty($message)): ?>
    <div class="alert alert-warning border-0 mb-3">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>
<div class="home-hero glass-card card shadow-lg">
    <div class="card-body">
        <?php if (!$tokens): ?>
            <p class="home-login-text mb-3">Log in met Strava om het actuele trainingschema van SpartaOne te bekijken.</p>
            <a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="d-inline-block">
                <img src="assets/img/strava/btn_strava_connect_with_orange_x2.svg" alt="Connect with Strava" class="home-strava-img">
            </a>
        <?php else: ?>
            <?php
            $entry = (!empty($nextTraining['entries']) && is_array($nextTraining['entries'])) ? $nextTraining['entries'][0] : null;
            ?>
            <?php if ($athleteError): ?>
                <div class="alert alert-danger mb-3"><?php echo $athleteError; ?></div>
            <?php endif; ?>
            <?php if (!empty($nextTraining) && $entry): ?>
                <div class="home-next">
                    <?php render_partial('cards/training_day', ['trainingDay' => $nextTraining]); ?>
                </div>
            <?php else: ?>
                <p class="text-muted">Geen komende training gevonden.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php if (!empty($upcomingGoals)): ?>
    <div class="mt-4">
        <?php foreach ($upcomingGoals as $goal): ?>
            <?php render_partial('cards/goal', ['goal' => $goal, 'goalOptions' => []]); ?>
        <?php endforeach; ?>
    </div>
<?php elseif ( $tokens ): ?>
    <div class="glass-card card shadow-lg mt-3">
        <div class="card-body">
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                <p class="mb-0 text-muted">Stel je eerste doel in.</p>
                <a href="?action=goals" class="btn btn-primary">Naar doelen</a>
            </div>
        </div>
    </div>
<?php endif; ?>
