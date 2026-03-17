<?php

/**
 * Return the on-disk workbook path for a supported age-grade sex key.
 */
function sparta_age_grade_workbook_path(string $sex): ?string
{
    $sex = strtolower(trim($sex));
    $paths = [
        'female' => __DIR__ . '/../assets/pdf/FemaleRoadStd2025.xlsx',
        'male' => __DIR__ . '/../assets/pdf/MaleRoadStd2025.xlsx',
    ];

    return $paths[$sex] ?? null;
}

/**
 * Calculate an athlete's age on a specific date.
 *
 * @param mixed $birthdate YYYY-MM-DD birthdate
 * @param mixed $targetDate YYYY-MM-DD event date
 */
function sparta_training_age_from_birthdate_on_date($birthdate, $targetDate): ?int
{
    $birthdate = trim((string)$birthdate);
    $targetDate = trim((string)$targetDate);
    if ($birthdate === '' || $targetDate === '') {
        return null;
    }

    $birthdateObj = DateTimeImmutable::createFromFormat('Y-m-d', $birthdate);
    $targetDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $targetDate);
    if (
        !$birthdateObj
        || $birthdateObj->format('Y-m-d') !== $birthdate
        || !$targetDateObj
        || $targetDateObj->format('Y-m-d') !== $targetDate
        || $birthdateObj > $targetDateObj
    ) {
        return null;
    }

    return $birthdateObj->diff($targetDateObj)->y;
}

/**
 * Normalize a stored gender key to the workbook's supported sex keys.
 *
 * @param mixed $gender
 */
function sparta_age_grade_normalize_gender($gender): string
{
    $gender = strtolower(trim((string)$gender));
    return in_array($gender, ['male', 'female'], true) ? $gender : '';
}

/**
 * Parse a result time to whole seconds.
 *
 * Supports MM:SS and H:MM:SS.
 *
 * @param mixed $time
 */
function sparta_age_grade_time_to_seconds($time): ?int
{
    $time = trim((string)$time);
    if ($time === '') {
        return null;
    }

    $parts = array_map('trim', explode(':', $time));
    if (count($parts) === 2) {
        [$minutes, $seconds] = $parts;
        if (!ctype_digit($minutes) || !ctype_digit($seconds)) {
            return null;
        }
        $minutes = (int)$minutes;
        $seconds = (int)$seconds;
        if ($seconds > 59) {
            return null;
        }
        return ($minutes * 60) + $seconds;
    }

    if (count($parts) === 3) {
        [$hours, $minutes, $seconds] = $parts;
        if (!ctype_digit($hours) || !ctype_digit($minutes) || !ctype_digit($seconds)) {
            return null;
        }
        $hours = (int)$hours;
        $minutes = (int)$minutes;
        $seconds = (int)$seconds;
        if ($minutes > 59 || $seconds > 59) {
            return null;
        }
        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }

    return null;
}

/**
 * Format an age-grade percentage for UI display.
 */
function sparta_format_age_grade_percent(?float $percent): string
{
    if ($percent === null) {
        return '-';
    }

    return number_format($percent, 1, '.', '') . '%';
}

/**
 * Load and cache the 2025 road age-grade table data from the Excel workbooks.
 *
 * @return array<string,mixed>
 */
function sparta_age_grade_data(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    foreach (['female', 'male'] as $sex) {
        $path = sparta_age_grade_workbook_path($sex);
        if ($path === null) {
            continue;
        }

        $parsed = sparta_parse_age_grade_workbook($path);
        if ($parsed !== null) {
            $cache[$sex] = $parsed;
        }
    }

    return $cache;
}

/**
 * Parse a single road age-grade workbook into open standards and age factors.
 *
 * @return array<string,mixed>|null
 */
function sparta_parse_age_grade_workbook(string $path): ?array
{
    if (!is_file($path) || !class_exists('ZipArchive') || !function_exists('simplexml_load_string')) {
        return null;
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return null;
    }

    $sharedStrings = sparta_age_grade_read_shared_strings($zip);
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($workbookXml === false || $relsXml === false) {
        $zip->close();
        return null;
    }

    $sheetPath = sparta_age_grade_find_sheet_path($workbookXml, $relsXml, ['Age Factors', 'Age Facctors']);
    if ($sheetPath === null) {
        $zip->close();
        return null;
    }

    $sheetXml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($sheetXml === false) {
        return null;
    }

    $rows = sparta_age_grade_parse_sheet_rows($sheetXml, $sharedStrings);
    if (!isset($rows[2], $rows[4])) {
        return null;
    }

    $eventColumns = [];
    $wantedColumns = [
        '5 km' => '5k',
        '10 km' => '10k',
        '10 Mile' => '10m',
        'H. Mar' => 'half-marathon',
        'Marathon' => 'marathon',
    ];
    foreach ($rows[2] as $cellRef => $label) {
        if (!isset($wantedColumns[$label])) {
            continue;
        }
        $eventColumns[$wantedColumns[$label]] = preg_replace('/\d+$/', '', $cellRef) ?: '';
    }

    if (count($eventColumns) !== count($wantedColumns)) {
        return null;
    }

    $data = [
        'open_standards' => [],
        'factors' => [],
    ];

    foreach ($eventColumns as $eventKey => $columnRef) {
        $openValue = $rows[4][$columnRef . '4'] ?? null;
        if (!is_numeric((string)$openValue)) {
            return null;
        }
        $data['open_standards'][$eventKey] = (int)round((float)$openValue);
    }

    foreach ($rows as $rowIndex => $cells) {
        if ($rowIndex < 6) {
            continue;
        }
        $ageValue = $cells['A' . $rowIndex] ?? $cells['B' . $rowIndex] ?? null;
        if (!is_numeric((string)$ageValue)) {
            continue;
        }
        $age = (int)round((float)$ageValue);
        $data['factors'][$age] = [];
        foreach ($eventColumns as $eventKey => $columnRef) {
            $factorValue = $cells[$columnRef . $rowIndex] ?? null;
            if (!is_numeric((string)$factorValue)) {
                unset($data['factors'][$age]);
                continue 2;
            }
            $data['factors'][$age][$eventKey] = (float)$factorValue;
        }
    }

    return $data;
}

/**
 * Read workbook shared strings.
 *
 * @return array<int,string>
 */
function sparta_age_grade_read_shared_strings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) {
        return [];
    }

    $root = simplexml_load_string($xml);
    if ($root === false) {
        return [];
    }

    $strings = [];
    foreach (($root->xpath('//*[local-name()="si"]') ?: []) as $item) {
        $textNodes = $item->xpath('.//*[local-name()="t"]') ?: [];
        $text = '';
        foreach ($textNodes as $textNode) {
            $text .= (string)$textNode;
        }
        $strings[] = $text;
    }

    return $strings;
}

/**
 * Resolve the worksheet XML path for one of the requested sheet names.
 *
 * @param array<int,string> $allowedNames
 */
function sparta_age_grade_find_sheet_path(string $workbookXml, string $relsXml, array $allowedNames): ?string
{
    $workbook = simplexml_load_string($workbookXml);
    $rels = simplexml_load_string($relsXml);
    if ($workbook === false || $rels === false) {
        return null;
    }

    $workbook->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $rels->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/package/2006/relationships');

    $sheetRelationshipId = null;
    foreach (($workbook->xpath('//a:sheets/a:sheet') ?: []) as $sheet) {
        $name = (string)($sheet['name'] ?? '');
        if (!in_array($name, $allowedNames, true)) {
            continue;
        }
        $sheetRelationshipId = (string)$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        break;
    }

    if ($sheetRelationshipId === null || $sheetRelationshipId === '') {
        return null;
    }

    foreach (($rels->xpath('//a:Relationship') ?: []) as $relationship) {
        if ((string)($relationship['Id'] ?? '') !== $sheetRelationshipId) {
            continue;
        }
        $target = (string)($relationship['Target'] ?? '');
        if ($target === '') {
            return null;
        }
        return strpos($target, 'xl/') === 0 ? $target : 'xl/' . ltrim($target, '/');
    }

    return null;
}

/**
 * Parse worksheet rows keyed by full cell reference.
 *
 * @param array<int,string> $sharedStrings
 * @return array<int,array<string,string>>
 */
function sparta_age_grade_parse_sheet_rows(string $sheetXml, array $sharedStrings): array
{
    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) {
        return [];
    }

    $rows = [];
    foreach (($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: []) as $row) {
        $rowIndex = (int)($row['r'] ?? 0);
        if ($rowIndex <= 0) {
            continue;
        }
        $rows[$rowIndex] = [];
        foreach (($row->xpath('./*[local-name()="c"]') ?: []) as $cell) {
            $cellRef = (string)($cell['r'] ?? '');
            $cellType = (string)($cell['t'] ?? '');
            $valueNodes = $cell->xpath('./*[local-name()="v"]') ?: [];
            if ($cellRef === '' || $valueNodes === []) {
                continue;
            }
            $value = (string)$valueNodes[0];
            if ($cellType === 's') {
                $sharedIndex = (int)$value;
                $value = $sharedStrings[$sharedIndex] ?? '';
            }
            $rows[$rowIndex][$cellRef] = $value;
        }
    }

    return $rows;
}

/**
 * Calculate a WMA road age-grade score.
 *
 * @param mixed $raceType
 * @param mixed $finishTime
 * @param mixed $gender
 * @param mixed $age
 * @return array<string,float|int>|null
 */
function sparta_calculate_age_grade($raceType, $finishTime, $gender, $age): ?array
{
    $raceType = sparta_normalize_training_race_type($raceType);
    $gender = sparta_age_grade_normalize_gender($gender);
    $age = is_numeric($age) ? (int)$age : null;
    $finishSeconds = sparta_age_grade_time_to_seconds($finishTime);

    if ($raceType === '' || $gender === '' || $age === null || $finishSeconds === null) {
        return null;
    }

    $data = sparta_age_grade_data();
    $sexData = $data[$gender] ?? null;
    $factor = $sexData['factors'][$age][$raceType] ?? null;
    $openStandard = $sexData['open_standards'][$raceType] ?? null;
    if (!is_numeric($factor) || !is_numeric($openStandard) || (float)$factor <= 0.0) {
        return null;
    }

    $ageGradedSeconds = $finishSeconds * (float)$factor;
    if ($ageGradedSeconds <= 0.0) {
        return null;
    }

    return [
        'age' => $age,
        'raw_seconds' => $finishSeconds,
        'factor' => (float)$factor,
        'open_standard_seconds' => (int)$openStandard,
        'age_graded_seconds' => $ageGradedSeconds,
        'percent' => ((int)$openStandard / $ageGradedSeconds) * 100,
    ];
}
