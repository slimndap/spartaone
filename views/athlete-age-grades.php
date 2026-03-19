<?php
$athlete = is_array($athlete ?? null) ? $athlete : [];
$athleteSettings = is_array($athleteSettings ?? null) ? $athleteSettings : [];
$targetTables = is_array($targetTables ?? null) ? $targetTables : [];
$fullName = trim((string)($athlete['firstname'] ?? '') . ' ' . (string)($athlete['lastname'] ?? ''));
if ($fullName === '') {
    $fullName = trim((string)($athlete['username'] ?? ''));
}
$fullName = $fullName !== '' ? $fullName : 'Onbekende sporter';
$initial = mb_substr($fullName, 0, 1);
$genderLabel = sparta_training_gender_label($athleteGender ?? '');
$birthdate = trim((string)($athleteSettings['birthdate'] ?? ''));
$missingProfile = $birthdate === '' || $athleteAge === null || sparta_age_grade_normalize_gender($athleteGender ?? '') === '';
?>

<div class="glass-card card shadow-lg mb-4">
    <div class="card-body">
        <?php if (empty($athleteId)): ?>
            <div class="alert alert-warning mb-0">No athlete selected. Provide an ID via <code>?action=athlete-age-grades&amp;id=123</code>.</div>
        <?php elseif ($athlete === []): ?>
            <div class="alert alert-info mb-0">No athlete found for ID <strong><?php echo htmlspecialchars((string)$athleteId, ENT_QUOTES, 'UTF-8'); ?></strong>.</div>
        <?php else: ?>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="athlete-avatar athlete-avatar-lg">
                        <?php if (!empty($athlete['profile'])): ?>
                            <img src="<?php echo htmlspecialchars((string)$athlete['profile'], ENT_QUOTES, 'UTF-8'); ?>" alt="Profile image">
                        <?php else: ?>
                            <span class="fw-bold fs-4"><?php echo htmlspecialchars($initial ?: '?', ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="section-label mb-1">Age Grade Doelen</div>
                        <h1 class="h4 mb-2 text-light"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <div class="d-flex flex-wrap gap-2 small text-secondary">
                            <?php if ($athleteAge !== null): ?>
                                <span>Leeftijd: <?php echo (int)$athleteAge; ?></span>
                            <?php endif; ?>
                            <?php if ($genderLabel !== ''): ?>
                                <span>Geslacht: <?php echo htmlspecialchars($genderLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php if ($birthdate !== ''): ?>
                                <span>Geboortedatum: <?php echo htmlspecialchars($birthdate, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="mb-0 mt-2 text-secondary small">
                            Deze tabellen zijn persoonlijk berekend voor <?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>
                            op basis van de ingevulde geboortedatum en het gekozen geslacht.
                        </p>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-light btn-sm" href="?action=athlete&amp;id=<?php echo urlencode((string)$athleteId); ?>">Terug naar sporter</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($athleteId) && $athlete !== []): ?>
    <div class="glass-card card shadow-lg mb-4">
        <div class="card-body">
            <div class="section-label mb-1">Uitleg</div>
            <h2 class="h5 text-light mb-3">Hoe je deze Age Grade-tabellen leest</h2>
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <div class="h6 text-light mb-2">Wat is Age Grade?</div>
                    <p class="mb-0 text-secondary">
                        Age Grade is een manier om loopprestaties eerlijker te vergelijken tussen leeftijden en geslachten.
                        Een percentage laat zien hoe dicht een resultaat ligt bij de officiële WMA-standaard voor jouw afstand,
                        leeftijd en geslacht. De tabellen hieronder zijn dus niet algemeen, maar afgestemd op deze sporter.
                    </p>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="h6 text-light mb-2">Waar gebruik je deze tabellen voor?</div>
                    <p class="mb-0 text-secondary">
                        Gebruik de tabellen om wedstrijddoelen te kiezen die passen bij jouw profiel. Je kunt per officiële afstand
                        zien welke tijd nodig is voor bijvoorbeeld 70%, 75% of 80%, en zo realistische seizoenstargets of
                        vergelijkbare doelen tussen verschillende afstanden bepalen.
                    </p>
                </div>
                <div class="col-12">
                    <div class="rounded-3 border px-3 py-3 small text-secondary" style="border-color: rgba(255,255,255,0.12) !important; background: rgba(255,255,255,0.03);">
                        <strong class="text-light d-block mb-2">Betekenis van de percentages</strong>
                        <span>&gt; 100% = Wereldrecordniveau</span><br>
                        <span>&gt; 90% = Wereldklasse</span><br>
                        <span>&gt; 80% = Nationale klasse</span><br>
                        <span>&gt; 70% = Regionale klasse</span><br>
                        <span>&gt; 60% = Lokaal niveau</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($missingProfile): ?>
        <div class="glass-card card shadow-lg">
            <div class="card-body">
                <div class="alert alert-warning mb-0">
                    Deze tabel kan pas worden berekend als de sporter een geboortedatum heeft ingevuld en een ondersteund geslacht heeft gekozen.
                    Ondersteund voor age grade: <strong>man</strong> en <strong>vrouw</strong>.
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($targetTables as $table): ?>
                <div class="col-12 col-xl-6">
                    <div class="glass-card card shadow-lg h-100">
                        <div class="card-body">
                            <div class="section-label mb-1">Officiële afstand</div>
                            <h2 class="h5 text-light mb-3"><?php echo htmlspecialchars((string)($table['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h2>
                            <div class="table-responsive training-results-table-wrapper">
                                <table class="table table-dark table-striped align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Age Grade</th>
                                            <th scope="col" class="text-end">Tijd</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($table['rows'] ?? []) as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(sparta_format_age_grade_percent((float)($row['percent'] ?? 0.0)), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-end fw-semibold"><?php echo htmlspecialchars((string)($row['time'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
