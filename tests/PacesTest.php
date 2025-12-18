<?php

use PHPUnit\Framework\TestCase;

class PacesTest extends TestCase
{
    public function testFindPaceRowChoosesNearest(): void
    {
        $table = get_pace_table();
        $row = find_pace_row($table, 241); // closest to 240 row

        $this->assertNotNull($row);
        $this->assertSame('40 min (4:00/km)', $row['label']);
    }

    public function testParseAndFormatPace(): void
    {
        $error = null;
        $seconds = parse_pace_to_seconds('04:30', $error);

        $this->assertNull($error);
        $this->assertSame(270, $seconds);
        $this->assertSame('04:30', format_pace_seconds($seconds));
    }

    public function testTempoPaceMapIncludesAeroobAndLegacyAerobe(): void
    {
        $row = [
            'five_k' => '4:00',
            'ten_k' => '4:10',
            'half_marathon' => '4:20',
            'marathon' => '4:30',
            'aerobe' => '5:00',
        ];

        $map = tempo_pace_map($row);

        $this->assertSame('5:00', $map['Aeroob']);
        $this->assertSame('5:00', $map['Aerobe']); // legacy label support
    }

    public function testComputeBasePaceFromInputString(): void
    {
        $settings = ['base_pace_input' => '04:10'];

        $this->assertSame(250, compute_base_pace_seconds($settings));
    }

    public function testResolveTempoPacesCachesContext(): void
    {
        $settings = ['base_pace_seconds' => 240];
        $result = resolve_tempo_paces($settings, null, null, true);

        $this->assertArrayHasKey('10K', $result);
        $cached = get_tempo_paces_context();
        $this->assertSame($result, $cached);
    }
}
