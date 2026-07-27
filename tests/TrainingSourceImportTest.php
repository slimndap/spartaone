<?php

use PHPUnit\Framework\TestCase;

class TrainingSourceImportTest extends TestCase
{
    public function testLarsSourceConvertsToCleanTaggedDenHaagSchedule(): void
    {
        $prepared = sparta_prepare_lars_den_haag_import();

        $this->assertSame('', $prepared['error']);
        $this->assertSame(123, $prepared['source_count']);
        $this->assertSame('2026-06-29', $prepared['start_date']);
        $this->assertSame('2026-11-01', $prepared['end_date']);

        $entries = [];
        foreach ($prepared['trainings'] as $day) {
            $this->assertCount(1, $day['entries']);
            $entries[] = $day['entries'][0];
        }
        $this->assertCount(123, array_unique(array_column($entries, 'source_key')));
        $this->assertSame(51, count(array_filter(
            $entries,
            static fn($entry) => stripos((string)$entry['notes'], 'optioneel') === 0
        )));
        foreach ($entries as $entry) {
            $this->assertSame('lars-den-haag-2026', $entry['source_id']);
            $this->assertStringStartsWith('Den Haag — ', $entry['title']);
            $this->assertStringNotContainsString('EKIDEN', $entry['title']);
            $this->assertStringNotContainsString('Krachttraining', $entry['notes']);
            $this->assertStringNotContainsString('/ Kracht', $entry['activity']);
        }

        $halfMarathon = $prepared['trainings'][array_search(
            '2026-09-20',
            array_column($prepared['trainings'], 'date'),
            true
        )]['entries'][0];
        $marathon = $prepared['trainings'][array_search(
            '2026-11-01',
            array_column($prepared['trainings'], 'date'),
            true
        )]['entries'][0];
        $this->assertSame('half-marathon', $halfMarathon['race_type']);
        $this->assertContains('Half Marathon', $halfMarathon['tempos']);
        $this->assertSame('marathon', $marathon['race_type']);
        $this->assertContains('Marathon', $marathon['tempos']);
        $this->assertStringNotContainsString('Optioneel', $marathon['notes']);
    }

    public function testReimportReplacesOnlyOwnedEntriesWithoutDuplicates(): void
    {
        $prepared = sparta_prepare_lars_den_haag_import();
        $existing = [
            [
                'date' => '2026-07-27',
                'entries' => [
                    [
                        'title' => 'Handmatig clubitem',
                        'activity' => 'Baan',
                        'distance' => '',
                        'notes' => '',
                        'tempos' => [],
                    ],
                    [
                        'title' => 'Oude Lars-versie',
                        'source_id' => 'lars-den-haag-2026',
                        'source_key' => 'lars-den-haag-2026:2026-07-27',
                    ],
                ],
            ],
        ];

        $first = sparta_replace_training_source(
            $existing,
            $prepared['trainings'],
            'lars-den-haag-2026'
        );
        $second = sparta_replace_training_source(
            $first['trainings'],
            $prepared['trainings'],
            'lars-den-haag-2026'
        );

        $this->assertSame(1, $first['removed_entries']);
        $this->assertSame(123, $first['added_entries']);
        $this->assertSame(123, $second['removed_entries']);
        $this->assertSame(123, $second['added_entries']);
        $this->assertSame(123, sparta_count_training_source_entries(
            $second['trainings'],
            'lars-den-haag-2026'
        ));
        $allEntries = array_merge(...array_map(
            static fn($day) => $day['entries'],
            $second['trainings']
        ));
        $this->assertCount(124, $allEntries);
        $this->assertContains('Handmatig clubitem', array_column($allEntries, 'title'));
    }

    public function testRemoveImportLeavesUnrelatedEntriesIntact(): void
    {
        $prepared = sparta_prepare_lars_den_haag_import();
        $withUnrelated = sparta_replace_training_source(
            [[
                'date' => '2026-07-27',
                'entries' => [['title' => 'Blijven staan']],
            ]],
            $prepared['trainings'],
            'lars-den-haag-2026'
        );

        $removed = sparta_remove_training_source(
            $withUnrelated['trainings'],
            'lars-den-haag-2026'
        );

        $this->assertSame(123, $removed['removed_entries']);
        $this->assertSame(0, sparta_count_training_source_entries(
            $removed['trainings'],
            'lars-den-haag-2026'
        ));
        $this->assertSame([[
            'date' => '2026-07-27',
            'entries' => [['title' => 'Blijven staan']],
        ]], $removed['trainings']);
    }

    public function testSourceValidationRejectsDuplicateKeysCountAndDateRange(): void
    {
        $payload = json_decode(
            (string)file_get_contents(sparta_lars_den_haag_source_path()),
            true
        );
        $duplicate = $payload;
        $duplicate['sessions'][1]['date'] = $duplicate['sessions'][0]['date'];
        $wrongCount = $payload;
        array_pop($wrongCount['sessions']);
        $wrongRange = $payload;
        $wrongRange['sessions'][0]['date'] = '2026-06-28';

        $this->assertStringContainsString(
            'dubbele source_key',
            sparta_convert_lars_den_haag_source($duplicate)['error']
        );
        $this->assertStringContainsString(
            'exact 123',
            sparta_convert_lars_den_haag_source($wrongCount)['error']
        );
        $this->assertStringContainsString(
            'datumbereik',
            sparta_convert_lars_den_haag_source($wrongRange)['error']
        );
    }

    public function testGenericMergePreservesSourceMetadata(): void
    {
        $existing = [[
            'date' => '2026-07-27',
            'entries' => [[
                'title' => 'Bronitem',
                'source_id' => 'lars-den-haag-2026',
                'source_key' => 'lars-den-haag-2026:2026-07-27',
            ]],
        ]];

        $merged = sparta_merge_trainings($existing, []);
        $entry = $merged[0]['entries'][0];

        $this->assertSame('lars-den-haag-2026', $entry['source_id']);
        $this->assertSame('lars-den-haag-2026:2026-07-27', $entry['source_key']);
    }

    public function testBackupAndAtomicSaveKeepRecoverablePreviousSchedule(): void
    {
        $dir = sys_get_temp_dir() . '/spartaone-training-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        $original = [['date' => '2026-01-01', 'entries' => [['title' => 'Origineel']]]];
        $changed = [['date' => '2026-01-02', 'entries' => [['title' => 'Gewijzigd']]]];

        try {
            $this->assertTrue(save_trainings($dir, $original));
            $backupPath = sparta_backup_trainings($dir);
            $this->assertIsString($backupPath);
            $this->assertTrue(save_trainings($dir, $changed));
            $this->assertSame($original, json_decode((string)file_get_contents($backupPath), true)['trainings']);
            $this->assertSame($changed, load_trainings($dir));
        } finally {
            foreach (glob($dir . '/backups/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($dir . '/backups')) {
                rmdir($dir . '/backups');
            }
            foreach (glob($dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }
}
