<?php

use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{
    /**
     * Ensure training day partial renders entry content.
     */
    public function testTrainingDayPartialRendersEntry(): void
    {
        $trainingDay = [
            'date' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'entries' => [
                [
                    'title' => 'Intervals',
                    'activity' => 'Track',
                    'distance' => '8x400m',
                    'tempos' => ['5K'],
                    'notes' => 'Keep it smooth',
                ],
            ],
        ];

        ob_start();
        render_partial('cards/training_day', ['trainingDay' => $trainingDay]);
        $html = ob_get_clean();

        $this->assertStringContainsString('Intervals', $html);
        $this->assertStringContainsString('Track', $html);
        $this->assertStringContainsString('8x400m', $html);
        $this->assertStringContainsString('Keep it smooth', $html);
    }

    /**
     * Ensure goal partial renders description and future date.
     */
    public function testGoalPartialRendersGoal(): void
    {
        $futureDate = (new DateTimeImmutable('today'))->modify('+5 days')->format('Y-m-d');
        $goal = [
            'description' => '10K under 45',
            'target_date' => $futureDate,
        ];

        ob_start();
        render_partial('cards/goal', ['goal' => $goal, 'goalOptions' => []]);
        $html = ob_get_clean();

        $this->assertStringContainsString('10K under 45', $html);
        $this->assertStringContainsString('goal-days-pill', $html);
    }
}
