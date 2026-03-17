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
}
