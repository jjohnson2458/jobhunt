<?php
/**
 * Job scraper interface — implementations call Playwright (via Node)
 * or eventually a paid API service. Each scraper accepts a track and
 * returns an array of normalized listing arrays.
 *
 * Normalized listing keys:
 *   source, source_url, source_id, title, company, location,
 *   is_remote, salary_min, salary_max, salary_text, description,
 *   posted_at
 */
interface JobScraper
{
    public function source(): string;
    public function fetch(array $track): array;
}

/**
 * PlaywrightScraper — base class that shells out to a Node.js
 * Playwright script and parses its JSON stdout. Each board has its
 * own .js file under scripts/playwright/.
 */
abstract class PlaywrightScraper implements JobScraper
{
    abstract public function source(): string;
    abstract protected function scriptName(): string;

    public function fetch(array $track): array
    {
        $script = BASE_PATH . '/scripts/playwright/' . $this->scriptName();
        if (!file_exists($script)) {
            throw new RuntimeException("Playwright script not found: $script");
        }
        $payload = json_encode([
            'keywords'    => $track['role_keywords']    ?? '',
            'locations'   => $track['locations']        ?? '',
            'salary_floor'=> (int)($track['salary_floor'] ?? 0),
            'remote_ok'   => (bool)($track['remote_ok']  ?? true),
        ]);
        $cmd = 'node ' . escapeshellarg($script) . ' ' . escapeshellarg($payload) . ' 2>&1';
        $out = shell_exec($cmd);
        if (!$out) { return []; }
        // The script may emit log lines + a final JSON line. Find the JSON.
        $lines = array_filter(array_map('trim', explode("\n", $out)));
        $json  = end($lines);
        $data  = json_decode($json, true);
        if (!is_array($data)) { return []; }
        $out = [];
        foreach ($data as $row) {
            $out[] = array_merge([
                'source'      => $this->source(),
                'source_url'  => null, 'source_id' => null,
                'title' => '', 'company' => '', 'location' => null,
                'is_remote' => 0, 'salary_min' => null, 'salary_max' => null, 'salary_text' => null,
                'description' => null, 'posted_at' => null,
            ], $row);
        }
        return $out;
    }
}

class IndeedScraper       extends PlaywrightScraper { public function source(): string { return 'indeed'; }       protected function scriptName(): string { return 'scrape_indeed.js'; } }
class ZipRecruiterScraper extends PlaywrightScraper { public function source(): string { return 'ziprecruiter'; } protected function scriptName(): string { return 'scrape_ziprecruiter.js'; } }
class MonsterScraper      extends PlaywrightScraper { public function source(): string { return 'monster'; }      protected function scriptName(): string { return 'scrape_monster.js'; } }
class LinkedInScraper     extends PlaywrightScraper { public function source(): string { return 'linkedin'; }     protected function scriptName(): string { return 'scrape_linkedin.js'; } }
