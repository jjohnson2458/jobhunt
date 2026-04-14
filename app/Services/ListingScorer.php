<?php
/**
 * Heuristic listing scorer (0-100). Cheap, no API call.
 *
 * +keyword hits, +salary at/above floor, +location/remote match,
 * -exclude keyword hits, -below salary floor.
 *
 * For deeper "is this actually a fit?" judgment, the cover letter
 * generator can later upgrade the score using Claude.
 */
class ListingScorer
{
    public function score(array $listing, array $track): array
    {
        $hay = strtolower(($listing['title'] ?? '') . "\n" . ($listing['description'] ?? '') . "\n" . ($listing['company'] ?? ''));
        $score = 50;
        $reasons = [];

        $keywords = array_filter(array_map('trim', explode(',', strtolower($track['role_keywords'] ?? ''))));
        $hits = 0;
        foreach ($keywords as $kw) {
            if ($kw !== '' && str_contains($hay, $kw)) { $hits++; }
        }
        if ($keywords) {
            $bonus = (int) round(min(40, ($hits / max(1, count($keywords))) * 60));
            $score += $bonus;
            $reasons[] = "{$hits}/" . count($keywords) . " keywords (+{$bonus})";
        }

        $excludes = array_filter(array_map('trim', explode(',', strtolower($track['exclude_keywords'] ?? ''))));
        foreach ($excludes as $ex) {
            if ($ex !== '' && str_contains($hay, $ex)) {
                $score -= 25;
                $reasons[] = "exclude '{$ex}' (-25)";
            }
        }

        $floor = (int)($track['salary_floor'] ?? 0);
        $sMin = (int)($listing['salary_min'] ?? 0);
        $sMax = (int)($listing['salary_max'] ?? 0);
        $sBest = max($sMin, $sMax);
        if ($floor > 0 && $sBest > 0) {
            if ($sBest >= $floor) { $score += 10; $reasons[] = "salary {$sBest}>={$floor} (+10)"; }
            else                  { $score -= 30; $reasons[] = "salary {$sBest}<{$floor} (-30)"; }
        }

        if (!empty($track['remote_ok']) && !empty($listing['is_remote'])) {
            $score += 8; $reasons[] = "remote (+8)";
        }
        $locs = array_filter(array_map('trim', explode(',', strtolower($track['locations'] ?? ''))));
        $loc  = strtolower($listing['location'] ?? '');
        foreach ($locs as $l) {
            if ($l && $l !== 'remote' && $loc && str_contains($loc, $l)) {
                $score += 6; $reasons[] = "location '{$l}' (+6)"; break;
            }
        }

        $score = max(0, min(100, $score));
        return ['score' => $score, 'reason' => implode(', ', $reasons)];
    }
}
