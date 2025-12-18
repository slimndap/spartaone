<?php

if (!function_exists('sparta_next_goals')) {
    /**
     * Return upcoming goals sorted by target date (>= today), limited.
     *
     * @param array<int,mixed> $goals
     * @param int              $limit
     * @return array<int,array<string,mixed>>
     */
    function sparta_next_goals(array $goals, int $limit = 3): array
    {
        $today = new DateTimeImmutable('today');
        $normalized = [];
        foreach ($goals as $goal) {
            if (function_exists('normalize_goal_entry')) {
                $entry = normalize_goal_entry($goal);
            } else {
                $entry = is_array($goal) ? $goal : ['description' => (string)$goal];
            }
            $dateStr = $entry['target_date'] ?? '';
            $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $dateStr) ?: false;
            if (!$dateObj || $dateObj->format('Y-m-d') !== $dateStr) {
                continue;
            }
            if ($dateObj < $today) {
                continue;
            }
            if (trim((string)($entry['description'] ?? '')) === '') {
                continue;
            }
            $normalized[] = $entry;
        }
        usort($normalized, static function ($a, $b) {
            return strcmp($a['target_date'] ?? '', $b['target_date'] ?? '');
        });
        if ($limit > 0) {
            $normalized = array_slice($normalized, 0, $limit);
        }
        return $normalized;
    }
}
