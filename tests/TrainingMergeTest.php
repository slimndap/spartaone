<?php

use PHPUnit\Framework\TestCase;

class TrainingMergeTest extends TestCase
{
    public function testMergeTrainingsKeepsExistingDaysAndAddsNewOnes(): void
    {
        $existing = [
            [
                'date' => '2026-03-01',
                'entries' => [
                    [
                        'title' => 'Existing session',
                        'activity' => 'Weg',
                        'distance' => '10 km',
                        'notes' => '',
                        'tempos' => [],
                    ],
                ],
            ],
        ];
        $imported = [
            [
                'date' => '2026-03-02',
                'entries' => [
                    [
                        'title' => 'Imported session',
                        'activity' => 'Baan',
                        'distance' => '8 km',
                        'notes' => '',
                        'tempos' => ['5K'],
                    ],
                ],
            ],
        ];

        $merged = sparta_merge_trainings($existing, $imported);

        $this->assertCount(2, $merged);
        $this->assertSame('2026-03-01', $merged[0]['date']);
        $this->assertSame('2026-03-02', $merged[1]['date']);
    }

    public function testMergeTrainingsAppendsEntriesOnExistingDate(): void
    {
        $existing = [
            [
                'date' => '2026-03-01',
                'entries' => [
                    [
                        'title' => 'Session A',
                        'activity' => 'Weg',
                        'distance' => '10 km',
                        'notes' => '',
                        'tempos' => [],
                    ],
                ],
            ],
        ];
        $imported = [
            [
                'date' => '2026-03-01',
                'entries' => [
                    [
                        'title' => 'Session B',
                        'activity' => 'Baan',
                        'distance' => '8 km',
                        'notes' => '',
                        'tempos' => ['3K'],
                    ],
                ],
            ],
        ];

        $merged = sparta_merge_trainings($existing, $imported);

        $this->assertCount(1, $merged);
        $this->assertCount(2, $merged[0]['entries']);
        $this->assertSame('Session A', $merged[0]['entries'][0]['title']);
        $this->assertSame('Session B', $merged[0]['entries'][1]['title']);
    }

    public function testMergeTrainingsDoesNotDuplicateExactEntries(): void
    {
        $existing = [
            [
                'date' => '2026-03-01',
                'entries' => [
                    [
                        'title' => 'Session A',
                        'activity' => 'Weg',
                        'distance' => '10 km',
                        'notes' => '',
                        'tempos' => [],
                    ],
                ],
            ],
        ];
        $imported = [
            [
                'date' => '2026-03-01',
                'entries' => [
                    [
                        'title' => 'Session A',
                        'activity' => 'Weg',
                        'distance' => '10 km',
                        'notes' => '',
                        'tempos' => [],
                    ],
                ],
            ],
        ];

        $merged = sparta_merge_trainings($existing, $imported);

        $this->assertCount(1, $merged);
        $this->assertCount(1, $merged[0]['entries']);
    }
}
