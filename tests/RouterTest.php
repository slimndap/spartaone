<?php

use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'trainingsDir' => __DIR__ . '/../data/trainings',
            'settingsDir' => __DIR__ . '/../data/settings',
        ];
    }

    /**
     * Provide a minimal state array that satisfies layout/home rendering.
     *
     * @param array<string,mixed> $overrides Override defaults for specific keys.
     * @return array<string,mixed>
     */
    private function baseState(array $overrides = []): array
    {
        $defaults = [
            'currentAthleteId' => null,
            'currentAthleteName' => null,
            'currentAthletePhoto' => null,
            'message' => null,
            'athlete' => [],
            'athleteError' => null,
            'nextTraining' => [],
            'upcomingGoals' => [],
            'goalMessage' => null,
            'goalError' => null,
            'saveGoalsSuccess' => null,
            'storageError' => null,
            'tokens' => null,
            'loginUrl' => '#',
        ];
        return array_merge($defaults, $overrides);
    }

    public function testUnknownActionFallsBackToHome(): void
    {
        $state = $this->baseState();

        ob_start();
        sparta_handle_request('nonexistent', $state, $this->config);
        $output = ob_get_clean();

        $this->assertStringContainsString('Log in met Strava', $output);
    }

    public function testAthletesRequiresLogin(): void
    {
        $state = $this->baseState();

        ob_start();
        sparta_handle_request('athletes', $state, $this->config);
        $output = ob_get_clean();

        $this->assertStringContainsString('Please log in to view athletes.', $output);
    }

    public function testAthletesRendersWhenLoggedIn(): void
    {
        $state = $this->baseState([
            'currentAthleteId' => '123',
            'otherAthletes' => [
                [
                    'id' => '456',
                    'firstname' => 'Alice',
                    'lastname' => 'Runner',
                ],
            ],
        ]);

        ob_start();
        sparta_handle_request('athletes', $state, $this->config);
        $output = ob_get_clean();

        $this->assertStringContainsString('Alice Runner', $output);
    }

    public function testNonAdminCannotRunDenHaagImport(): void
    {
        $dir = sys_get_temp_dir() . '/spartaone-router-' . bin2hex(random_bytes(4));
        $trainingsDir = $dir . '/trainings';
        $settingsDir = $dir . '/settings';
        mkdir($settingsDir, 0775, true);
        $original = [['date' => '2026-01-01', 'entries' => [['title' => 'Blijven staan']]]];
        save_trainings($trainingsDir, $original);
        $previousPost = $_POST;
        $previousGet = $_GET;
        $_POST = ['lars_import_action' => 'replace'];
        $_GET = ['action' => 'training'];

        try {
            ob_start();
            sparta_handle_training($this->baseState([
                'currentAthleteId' => '123',
                'isAdmin' => false,
                'athletes' => [],
            ]), [
                'trainingsDir' => $trainingsDir,
                'settingsDir' => $settingsDir,
            ], 'training');
            ob_end_clean();

            $this->assertSame($original, load_trainings($trainingsDir));
            $this->assertSame(0, sparta_count_training_source_entries(
                load_trainings($trainingsDir),
                'lars-den-haag-2026'
            ));
        } finally {
            $_POST = $previousPost;
            $_GET = $previousGet;
            foreach (glob($trainingsDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($trainingsDir)) {
                rmdir($trainingsDir);
            }
            if (is_dir($settingsDir)) {
                rmdir($settingsDir);
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testAdminCanPreviewDenHaagImportWithoutWriting(): void
    {
        $dir = sys_get_temp_dir() . '/spartaone-router-' . bin2hex(random_bytes(4));
        $trainingsDir = $dir . '/trainings';
        $settingsDir = $dir . '/settings';
        mkdir($settingsDir, 0775, true);
        $original = [['date' => '2026-01-01', 'entries' => [['title' => 'Blijven staan']]]];
        save_trainings($trainingsDir, $original);
        $previousPost = $_POST;
        $previousGet = $_GET;
        $_POST = ['lars_import_action' => 'preview'];
        $_GET = ['action' => 'training'];

        try {
            ob_start();
            sparta_handle_training($this->baseState([
                'currentAthleteId' => '123',
                'isAdmin' => true,
                'athletes' => [],
            ]), [
                'trainingsDir' => $trainingsDir,
                'settingsDir' => $settingsDir,
            ], 'training');
            $output = ob_get_clean();

            $this->assertStringContainsString('Voorbeeld: 0 bestaande bronitems', $output);
            $this->assertStringContainsString('door 123 sessies', $output);
            $this->assertSame($original, load_trainings($trainingsDir));
        } finally {
            $_POST = $previousPost;
            $_GET = $previousGet;
            foreach (glob($trainingsDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($trainingsDir)) {
                rmdir($trainingsDir);
            }
            if (is_dir($settingsDir)) {
                rmdir($settingsDir);
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testAdminManualEditPreservesSourceMetadataAndCreatesBackup(): void
    {
        $dir = sys_get_temp_dir() . '/spartaone-router-' . bin2hex(random_bytes(4));
        $trainingsDir = $dir . '/trainings';
        $settingsDir = $dir . '/settings';
        mkdir($settingsDir, 0775, true);
        $original = [[
            'date' => '2026-08-01',
            'entries' => [[
                'title' => 'Voor wijziging',
                'activity' => 'Run',
                'distance' => '',
                'notes' => '',
                'race_type' => '',
                'finish_times' => [],
                'tempos' => ['Aeroob'],
                'source_id' => 'lars-den-haag-2026',
                'source_key' => 'lars-den-haag-2026:2026-08-01',
            ]],
        ]];
        save_trainings($trainingsDir, $original);
        $previousPost = $_POST;
        $previousGet = $_GET;
        $_POST = [
            'save_training' => '1',
            'edit_date' => '2026-08-01',
            'edit_idx' => '0',
            'title' => 'Na wijziging',
            'activity' => 'Run',
            'distance' => '',
            'notes' => '',
            'race_type' => '',
            'finish_times' => [],
            'tempos' => ['Aeroob'],
        ];
        $_GET = ['action' => 'training'];

        try {
            ob_start();
            sparta_handle_training($this->baseState([
                'currentAthleteId' => '123',
                'isAdmin' => true,
                'athletes' => [],
            ]), [
                'trainingsDir' => $trainingsDir,
                'settingsDir' => $settingsDir,
            ], 'training');
            ob_end_clean();

            $saved = load_trainings($trainingsDir);
            $this->assertSame('Na wijziging', $saved[0]['entries'][0]['title']);
            $this->assertSame('lars-den-haag-2026', $saved[0]['entries'][0]['source_id']);
            $this->assertSame('lars-den-haag-2026:2026-08-01', $saved[0]['entries'][0]['source_key']);
            $backups = glob($trainingsDir . '/backups/shared-*.json') ?: [];
            $this->assertCount(1, $backups);
            $this->assertSame($original, load_trainings(dirname($backups[0]), basename($backups[0], '.json')));
        } finally {
            $_POST = $previousPost;
            $_GET = $previousGet;
            foreach (glob($trainingsDir . '/backups/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($trainingsDir . '/backups')) {
                rmdir($trainingsDir . '/backups');
            }
            foreach (glob($trainingsDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($trainingsDir)) {
                rmdir($trainingsDir);
            }
            if (is_dir($settingsDir)) {
                rmdir($settingsDir);
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }
}
