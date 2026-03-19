<?php

use PHPUnit\Framework\TestCase;

class AgeGradeTest extends TestCase
{
    public function testEventDayAgeUsesRaceDate(): void
    {
        $this->assertSame(39, sparta_training_age_from_birthdate_on_date('1985-08-20', '2025-08-19'));
        $this->assertSame(40, sparta_training_age_from_birthdate_on_date('1985-08-20', '2025-08-20'));
    }

    public function testTimeParserSupportsShortAndLongFormats(): void
    {
        $this->assertSame(1200, sparta_age_grade_time_to_seconds('20:00'));
        $this->assertSame(5400, sparta_age_grade_time_to_seconds('1:30:00'));
        $this->assertNull(sparta_age_grade_time_to_seconds('1:60:00'));
    }

    public function testAgeGradeDataLoadsOpenStandardsFrom2025Sheets(): void
    {
        $data = sparta_age_grade_data();

        $this->assertSame(769, $data['male']['open_standards']['5k']);
        $this->assertSame(2832, $data['female']['open_standards']['10m']);
    }

    public function testCalculatesMaleAgeGradePercent(): void
    {
        $result = sparta_calculate_age_grade('5k', '20:00', 'male', 40);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(0.9475, $result['factor'], 0.00001);
        $this->assertEqualsWithDelta(67.6341, $result['percent'], 0.0001);
    }

    public function testCalculatesFemaleAgeGradePercent(): void
    {
        $result = sparta_calculate_age_grade('half-marathon', '1:45:00', 'female', 50);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(0.8753, $result['factor'], 0.00001);
        $this->assertEqualsWithDelta(68.4029, $result['percent'], 0.0001);
    }

    public function testUnsupportedGenderReturnsNull(): void
    {
        $this->assertNull(sparta_calculate_age_grade('10k', '45:00', 'non_binary', 35));
    }

    public function testFormatsElapsedSecondsForShortAndLongRaceTimes(): void
    {
        $this->assertSame('19:43', sparta_format_elapsed_time(1183));
        $this->assertSame('1:24:05', sparta_format_elapsed_time(5045));
    }

    public function testCalculatesTargetTimeForAgeGradePercent(): void
    {
        $target = sparta_calculate_age_grade_target_time('5k', 'male', 40, 70);

        $this->assertNotNull($target);
        $this->assertSame(1159, $target['raw_seconds']);
    }

    public function testBuildsTargetTablesFromSixtyToOneHundredPercent(): void
    {
        $tables = sparta_build_age_grade_target_tables('female', 50);

        $this->assertCount(5, $tables);
        $this->assertSame('5k', $tables[0]['race_type']);
        $this->assertCount(9, $tables[0]['rows']);
        $this->assertSame(100.0, $tables[0]['rows'][0]['percent']);
        $this->assertSame(60.0, $tables[0]['rows'][8]['percent']);
    }

    public function testSelectBestRankedResultsAveragesBestFive(): void
    {
        $result = sparta_select_best_ranked_results([
            ['date' => '2024-01-01', 'percent' => 92.0, 'weight' => 0.5, 'weighted_score' => 46.0],
            ['date' => '2025-09-01', 'percent' => 88.0, 'weight' => 1.0, 'weighted_score' => 88.0],
            ['date' => '2025-04-01', 'percent' => 70.0, 'weight' => 1.0, 'weighted_score' => 70.0],
        ]);

        $this->assertEqualsWithDelta(81.6, $result['score'], 0.0001);
        $this->assertSame(3, $result['counted_results']);
    }

    public function testRecencyWeightPenalizesOlderResultsProgressively(): void
    {
        $this->assertEqualsWithDelta(1.0, sparta_ranking_recency_weight(0), 0.00001);
        $this->assertLessThan(sparta_ranking_recency_weight(12), sparta_ranking_recency_weight(24));
        $this->assertEqualsWithDelta(0.94, sparta_ranking_recency_weight(35), 0.00001);
    }
}
