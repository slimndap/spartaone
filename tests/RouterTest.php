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
     * @param array $overrides
     * @return array<string,mixed>
     */
    private function baseState(array $overrides = []): array
    {
        $defaults = [
            'currentAthleteId' => null,
            'currentAthleteName' => null,
            'currentAthletePhoto' => null,
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
}
