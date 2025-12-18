<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// Cache-busting for local CSS assets
$bootstrapCssPath = __DIR__ . '/../assets/css/bootstrap.min.css';
$customCssPath = __DIR__ . '/../assets/css/custom.css';
$bootstrapCssVer = file_exists($bootstrapCssPath) ? filemtime($bootstrapCssPath) : time();
$customCssVer = file_exists($customCssPath) ? filemtime($customCssPath) : time();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SpartaOne</title>
    <link rel="icon" type="image/png" href="assets/img/spartaone.png">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css?v=<?php echo htmlspecialchars($bootstrapCssVer, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="assets/css/custom.css?v=<?php echo htmlspecialchars($customCssVer, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="assets/img/spartaone.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#0b0b0f">
</head>
<?php
$bodyClasses = [];
$actionClass = $currentAction ?? ($_GET['action'] ?? 'home');
$actionSlug = preg_replace('/[^a-z0-9-]/i', '', strtolower($actionClass));
if ($actionSlug !== 'training') {
    $bodyClasses[] = 'bg-light';
}
$bodyClasses[] = 'page-' . ($actionSlug ?: 'home');
?>
<body class="<?php echo implode(' ', $bodyClasses); ?>">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-2" href="?">
                <img src="assets/img/spartaone.png" alt="SpartaOne logo" width="30" height="32">
                <span>SpartaOne</span>
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <?php if (!empty($currentAthleteName)): ?>
                    <div class="dropdown">
                        <button class="btn btn-dark p-0 border-0 d-flex align-items-center gap-2 dropdown-toggle" type="button" id="athleteMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if (!empty($currentAthletePhoto)): ?>
                                <img src="<?php echo htmlspecialchars($currentAthletePhoto, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile photo" class="rounded-circle border border-light" width="32" height="32">
                            <?php else: ?>
                                <span class="badge bg-success text-wrap mb-0"><?php echo htmlspecialchars($currentAthleteName, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="athleteMenu">
                            <li><span class="dropdown-item-text fw-semibold"><?php echo htmlspecialchars($currentAthleteName, ENT_QUOTES, 'UTF-8'); ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="?action=logout">Logout</a></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="container my-4 pb-5 pb-md-5">
        <?php include $viewPath; ?>
    </main>
    <?php
    $navAction = $currentAction ?? ($_GET['action'] ?? 'home');
    $navItems = [
        ['action' => 'home', 'label' => 'Home', 'icon' => 'bi-house-fill'],
        ['action' => 'athletes', 'label' => 'Sporters', 'icon' => 'bi-people-fill'],
        ['action' => 'goals', 'label' => 'Goals', 'icon' => 'bi-bullseye'],
        ['action' => 'training', 'label' => 'Schema', 'icon' => 'bi-calendar2-week-fill'],
        ['action' => 'tempos', 'label' => "Tempo's", 'icon' => 'bi-speedometer2'],
    ];
    ?>
    <nav class="bottom-nav">
        <div class="bottom-nav__inner">
            <?php foreach ($navItems as $item): 
                $active = $navAction === $item['action'];
                $href = $item['action'] === 'home' ? '?' : '?action=' . urlencode($item['action']);
            ?>
                <a class="bottom-nav__item<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo $href; ?>">
                    <i class="bi <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
