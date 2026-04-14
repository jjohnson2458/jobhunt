<?php
/**
 * Parses a saved Indeed job posting HTML page (Ctrl+S from browser).
 * Extracts title, company, location, salary, full description, apply info.
 */
class IndeedPageParser
{
    /**
     * Parse an Indeed saved HTML file into a normalized listing array.
     *
     * @param string $filePath Path to the .html file
     * @return array Normalized listing data
     */
    public function parse(string $filePath): array
    {
        $html = file_get_contents($filePath);
        $filename = basename($filePath);

        // Title: from <title> tag or filename
        $title = '';
        if (preg_match('#<title[^>]*>([^<]+)</title>#i', $html, $m)) {
            $title = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            // Strip " - Indeed.com" suffix and location
            $title = preg_replace('/\s*[-–]\s*(.*Indeed\.com|.*\d{5}).*$/i', '', $title);
        }
        if (!$title) {
            // Fallback to filename
            $title = preg_replace('/\s*[-–].*Indeed.*$/i', '', pathinfo($filename, PATHINFO_FILENAME));
        }
        $title = trim($title);

        // Company: from JSON blob (most reliable) or DOM
        $company = '';
        if (preg_match('#"companyName"\s*:\s*"([^"]+)"#', $html, $m)) {
            $company = trim(json_decode('"' . $m[1] . '"') ?? $m[1]);
        } elseif (preg_match('#data-testid="companyName"[^>]*>.*?<span[^>]*>([^<]+)</span>#is', $html, $m)) {
            $company = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }

        // Location: data-testid="companyLocation" or "job-location"
        $location = '';
        if (preg_match('#data-testid="(?:companyLocation|job-location)"[^>]*>(?:<[^>]*>)*([^<]+)#is', $html, $m)) {
            $location = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        // Also try from filename: "Title – City, ST ZIP - Indeed.com"
        if (!$location && preg_match('/[-–]\s*(.+?,\s*[A-Z]{2}(?:\s+\d{5})?)\s*[-–]/i', $filename, $m)) {
            $location = trim($m[1]);
        }

        // Remote check
        $isRemote = (bool) preg_match('/\bremote\b/i', $title . ' ' . $location . ' ' . $html);

        // Salary
        $salaryText = '';
        $salaryMin = null; $salaryMax = null;
        // Look for structured salary or inline text
        if (preg_match('#(?:salary|pay|compensation)[^<]*?(\$[\d,]+(?:\.\d+)?(?:\s*[-–]\s*\$?[\d,]+(?:\.\d+)?)?(?:\s*(?:per|a|/)\s*(?:hour|year|yr|hr|week|month))?)[^<]*#i', $html, $m)) {
            $salaryText = trim($m[1]);
        } elseif (preg_match('#\$[\d,]+(?:\.\d+)?\s*[-–]\s*\$?[\d,]+(?:\.\d+)?\s*(?:per|a|/)\s*(?:hour|year|yr|hr)#i', $html, $m)) {
            $salaryText = trim($m[0]);
        }
        // Parse min/max
        if (preg_match_all('/\$([\d,]+)/', $salaryText, $nums)) {
            $vals = array_map(fn($v) => (int) str_replace(',', '', $v), $nums[1]);
            sort($vals);
            $salaryMin = $vals[0] ?? null;
            $salaryMax = $vals[count($vals) - 1] ?? null;
            // Normalize hourly to annual for comparison
            if (preg_match('/hour|hr/i', $salaryText) && $salaryMax < 500) {
                $salaryMin = (int)($salaryMin * 2080);
                $salaryMax = (int)($salaryMax * 2080);
            }
        }

        // Full job description
        $description = '';
        // Try JSON sanitizedJobDescription first (most complete)
        if (preg_match('#"sanitizedJobDescription"\s*:\s*"(.*?)"(?:,"|}\s*$)#s', $html, $m)) {
            $raw = json_decode('"' . $m[1] . '"');
            if ($raw) $description = $this->cleanHtml($raw);
        }
        // Fallback: DOM id="jobDescriptionText"
        if (!$description && preg_match('#id="jobDescriptionText"[^>]*>(.*?)</div>\s*<div#is', $html, $m)) {
            $description = $this->cleanHtml($m[1]);
        }
        // Fallback: all <p> tags
        if (!$description) {
            preg_match_all('#<p[^>]*>(.*?)</p>#is', $html, $ps);
            $desc = '';
            foreach ($ps[1] ?? [] as $p) {
                $text = trim(strip_tags($p));
                if (strlen($text) > 20) $desc .= $text . "\n\n";
            }
            $description = trim($desc);
        }

        // Apply info: look for email, reference number, deadline
        $applyInfo = '';
        if (preg_match('/(?:submit|send|email|apply)[^.]*?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $description, $m)) {
            $applyInfo .= "Email: {$m[1]}\n";
        }
        if (preg_match('/#?\d{2,}-\d{3,}[A-Z]*\s+\w+/i', $description, $m)) {
            $applyInfo .= "Ref: {$m[0]}\n";
        }
        if (preg_match('/(?:by|deadline|before)\s+(\w+\s+\d{1,2},?\s+\d{4})/i', $description, $m)) {
            $applyInfo .= "Deadline: {$m[1]}\n";
        }

        // Source URL from the saved page
        $sourceUrl = '';
        if (preg_match('#requestURL":"(https?://[^"]+viewjob[^"]*)"#', $html, $m)) {
            $sourceUrl = stripslashes(urldecode($m[1]));
        }
        $sourceId = '';
        if (preg_match('/jk=([a-f0-9]+)/', $sourceUrl ?: $filename, $m)) {
            $sourceId = $m[1];
        }

        return [
            'source'      => 'indeed',
            'source_url'  => $sourceUrl ?: null,
            'source_id'   => $sourceId ?: null,
            'title'       => $title,
            'company'     => $company ?: '(unknown)',
            'location'    => $location,
            'is_remote'   => $isRemote ? 1 : 0,
            'salary_min'  => $salaryMin,
            'salary_max'  => $salaryMax,
            'salary_text' => $salaryText ?: null,
            'description' => $description,
            'posted_at'   => null,
            'apply_info'  => $applyInfo,
            'filename'    => $filename,
        ];
    }

    private function cleanHtml(string $html): string
    {
        $html = str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>', '</div>'], "\n", $html);
        $html = str_replace('<li>', '• ', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }
}
