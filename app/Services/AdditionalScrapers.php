<?php
/**
 * Feed/API-based scrapers for job boards that don't require Playwright
 * or Gmail parsing. These use public RSS feeds and REST APIs, bypassing
 * Cloudflare entirely.
 *
 * Included scrapers:
 *   - DiceScraper         (DHI Group public search API)
 *   - WeWorkRemotelyScraper (RSS feeds)
 *   - CraigslistScraper    (RSS feed — Buffalo region)
 *
 * All three implement the JobScraper interface defined in JobScraper.php.
 */

// ─────────────────────────────────────────────────────────────────────
//  Shared helper trait for curl + RSS parsing
// ─────────────────────────────────────────────────────────────────────

trait FeedScraperHelpers
{
    /**
     * Execute a curl GET request. Returns the response body or false on failure.
     */
    protected function curlGet(string $url, array $headers = [], int $timeout = 15): string|false
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            error_log("[{$this->source()}] curl error: $err");
            return false;
        }
        if ($code < 200 || $code >= 400) {
            error_log("[{$this->source()}] HTTP $code from $url");
            return false;
        }
        return $body;
    }

    /**
     * Parse an RSS/XML string into a SimpleXMLElement, suppressing errors.
     */
    protected function parseRss(string $xml): ?SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_clear_errors();
        if ($feed === false) {
            error_log("[{$this->source()}] Failed to parse RSS XML");
            return null;
        }
        return $feed;
    }

    /**
     * Try to extract salary numbers from a text string.
     * Returns [min, max] or [null, null].
     */
    protected function extractSalary(string $text): array
    {
        // Match patterns like "$80,000 - $120,000" or "$50/hr" or "$90K"
        if (preg_match('/\$\s*([\d,]+)\s*[kK]\s*(?:[-–—to]+\s*\$?\s*([\d,]+)\s*[kK])?/', $text, $m)) {
            $min = (int)str_replace(',', '', $m[1]) * 1000;
            $max = isset($m[2]) && $m[2] ? (int)str_replace(',', '', $m[2]) * 1000 : null;
            return [$min, $max];
        }
        if (preg_match('/\$\s*([\d,]+)(?:\.\d+)?\s*(?:[-–—to]+\s*\$?\s*([\d,]+)(?:\.\d+)?)?/', $text, $m)) {
            $min = (int)str_replace(',', '', $m[1]);
            $max = isset($m[2]) && $m[2] ? (int)str_replace(',', '', $m[2]) : null;
            // If the number is suspiciously low, it might be hourly — annualize
            if ($min > 0 && $min < 500) {
                $min *= 2080;
                if ($max) $max *= 2080;
            }
            return [$min, $max];
        }
        return [null, null];
    }

    /**
     * Strip HTML tags and collapse whitespace.
     */
    protected function cleanText(string $html): string
    {
        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $html));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Check whether a listing title should be excluded based on track keywords.
     */
    protected function shouldExclude(string $title, string $description, array $track): bool
    {
        $excludes = $track['exclude_keywords'] ?? '';
        if (!$excludes) return false;
        $keywords = array_filter(array_map('trim', preg_split('/[,;|]+/', $excludes)));
        $haystack = strtolower($title . ' ' . $description);
        foreach ($keywords as $kw) {
            if (str_contains($haystack, strtolower($kw))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build a normalized listing row with defaults.
     */
    protected function listing(array $data): array
    {
        return array_merge([
            'source'      => $this->source(),
            'source_url'  => null,
            'source_id'   => null,
            'title'       => '',
            'company'     => '(unknown)',
            'location'    => '',
            'is_remote'   => 0,
            'salary_text' => null,
            'salary_min'  => null,
            'salary_max'  => null,
            'description' => null,
            'posted_at'   => null,
        ], $data);
    }
}


// ─────────────────────────────────────────────────────────────────────
//  1. Dice.com — DHI Group public search API
// ─────────────────────────────────────────────────────────────────────

class DiceScraper implements JobScraper
{
    use FeedScraperHelpers;

    private const API_BASE = 'https://job-search-api.svc.dhigroupinc.com/v1/dice/jobs/search';

    public function source(): string { return 'dice'; }

    public function fetch(array $track): array
    {
        $keywords  = $track['role_keywords'] ?? '';
        $locations = $track['locations']     ?? '';
        $remoteOk  = (bool)($track['remote_ok'] ?? true);

        if (!$keywords) return [];

        $params = [
            'q'          => $keywords,
            'countryCode2' => 'US',
            'radius'     => '30',
            'radiusUnit' => 'mi',
            'page'       => '1',
            'pageSize'   => '50',
            'filters.postedDate' => 'THREE', // last 3 days
            'language'   => 'en',
        ];

        if ($locations) {
            // Use the first location from a comma-separated list
            $loc = trim(explode(',', $locations)[0]);
            $params['location'] = $loc;
        }

        $url = self::API_BASE . '?' . http_build_query($params);

        $body = $this->curlGet($url, [
            'Accept: application/json',
            'x-api-key: 1YAt0R9wBg4WfsF9VB2778F5CHLAPMVW3WAZcKd8',
        ]);

        if (!$body) return [];

        $json = json_decode($body, true);
        if (!is_array($json) || empty($json['data'])) {
            error_log("[dice] API returned no data or unexpected format");
            return [];
        }

        $listings = [];
        foreach ($json['data'] as $job) {
            $title       = $job['title'] ?? '';
            $company     = $job['companyName'] ?? '(unknown)';
            $location    = $job['employmentDetails']['location'] ?? ($job['location'] ?? '');
            $description = $job['description'] ?? ($job['summary'] ?? '');
            $jobId       = $job['id'] ?? ($job['detailsPageUrl'] ?? '');
            $detailUrl   = $job['detailsPageUrl'] ?? '';
            $postedDate  = $job['postedDate'] ?? ($job['dateCreated'] ?? null);
            $salary      = $job['salary'] ?? ($job['compensation'] ?? '');

            // Normalize the detail URL
            if ($detailUrl && !str_starts_with($detailUrl, 'http')) {
                $detailUrl = 'https://www.dice.com' . $detailUrl;
            }

            // Check remote
            $isRemote = 0;
            $remoteText = strtolower($title . ' ' . $location . ' ' . ($job['workFromHomeAvailability'] ?? ''));
            if (str_contains($remoteText, 'remote') || ($job['isRemote'] ?? false)) {
                $isRemote = 1;
            }

            // Skip non-remote if remote-only track and listing isn't remote
            // (but still include if remote_ok is true — that means remote is acceptable, not required)

            // Clean description
            $descClean = $this->cleanText($description);
            if (strlen($descClean) > 1000) {
                $descClean = substr($descClean, 0, 1000);
            }

            // Exclude check
            if ($this->shouldExclude($title, $descClean, $track)) continue;

            // Salary parsing
            $salaryText = null;
            $salaryMin  = null;
            $salaryMax  = null;
            if (is_string($salary) && $salary) {
                $salaryText = $salary;
                [$salaryMin, $salaryMax] = $this->extractSalary($salary);
            } elseif (is_array($salary)) {
                $salaryText = ($salary['min'] ?? '') . ' - ' . ($salary['max'] ?? '');
                $salaryMin  = isset($salary['min']) ? (int)$salary['min'] : null;
                $salaryMax  = isset($salary['max']) ? (int)$salary['max'] : null;
            }

            // Parse posted date
            $postedAt = null;
            if ($postedDate) {
                try {
                    $dt = new DateTime($postedDate);
                    $postedAt = $dt->format('Y-m-d H:i:s');
                } catch (Throwable $e) {
                    // leave null
                }
            }

            $listings[] = $this->listing([
                'source_url'  => $detailUrl,
                'source_id'   => (string)$jobId,
                'title'       => $title,
                'company'     => $company,
                'location'    => $location,
                'is_remote'   => $isRemote,
                'salary_text' => $salaryText,
                'salary_min'  => $salaryMin,
                'salary_max'  => $salaryMax,
                'description' => $descClean,
                'posted_at'   => $postedAt,
            ]);
        }

        return $listings;
    }
}


// ─────────────────────────────────────────────────────────────────────
//  2. We Work Remotely — RSS feeds
// ─────────────────────────────────────────────────────────────────────

class WeWorkRemotelyScraper implements JobScraper
{
    use FeedScraperHelpers;

    private const FEEDS = [
        'https://weworkremotely.com/categories/remote-back-end-programming-jobs.rss',
        'https://weworkremotely.com/categories/remote-full-stack-programming-jobs.rss',
    ];

    public function source(): string { return 'weworkremotely'; }

    public function fetch(array $track): array
    {
        $keywords = strtolower($track['role_keywords'] ?? '');
        $keywordList = array_filter(array_map('trim', preg_split('/[,;|]+/', $keywords)));

        $listings = [];
        $seen = [];

        foreach (self::FEEDS as $feedUrl) {
            $xml = $this->curlGet($feedUrl);
            if (!$xml) continue;

            $feed = $this->parseRss($xml);
            if (!$feed) continue;

            $items = $feed->channel->item ?? [];
            foreach ($items as $item) {
                $title   = (string)($item->title ?? '');
                $link    = (string)($item->link ?? '');
                $guid    = (string)($item->guid ?? $link);
                $desc    = (string)($item->description ?? '');
                $pubDate = (string)($item->pubDate ?? '');

                if (!$title || !$link) continue;

                // Dedupe across feeds
                if (isset($seen[$guid])) continue;
                $seen[$guid] = true;

                // Extract company from title — WWR format is usually "Company: Job Title"
                $company = '(unknown)';
                $jobTitle = $title;
                if (str_contains($title, ':')) {
                    $parts = explode(':', $title, 2);
                    $company  = trim($parts[0]);
                    $jobTitle = trim($parts[1]);
                }

                $descClean = $this->cleanText($desc);
                if (strlen($descClean) > 1000) {
                    $descClean = substr($descClean, 0, 1000);
                }

                // Keyword filtering: if keywords are set, at least one must appear
                if ($keywordList) {
                    $haystack = strtolower($jobTitle . ' ' . $descClean);
                    $match = false;
                    foreach ($keywordList as $kw) {
                        if (str_contains($haystack, $kw)) { $match = true; break; }
                    }
                    if (!$match) continue;
                }

                // Exclude check
                if ($this->shouldExclude($jobTitle, $descClean, $track)) continue;

                // Salary extraction from description
                $salaryText = null;
                $salaryMin  = null;
                $salaryMax  = null;
                if (preg_match('/\$[\d,]+(?:\s*[-–—to]+\s*\$?[\d,]+)?(?:\s*(?:\/yr|\/year|per year|annually|\/hr|\/hour|per hour|[kK]))?/', $descClean, $sm)) {
                    $salaryText = $sm[0];
                    [$salaryMin, $salaryMax] = $this->extractSalary($salaryText);
                }

                // Posted date
                $postedAt = null;
                if ($pubDate) {
                    try {
                        $dt = new DateTime($pubDate);
                        $postedAt = $dt->format('Y-m-d H:i:s');
                    } catch (Throwable $e) {}
                }

                // Extract source_id from URL (last path segment)
                $sourceId = $guid;
                if (preg_match('#/(\d+)#', $link, $idMatch)) {
                    $sourceId = $idMatch[1];
                }

                $listings[] = $this->listing([
                    'source_url'  => $link,
                    'source_id'   => $sourceId,
                    'title'       => $jobTitle,
                    'company'     => $company,
                    'location'    => 'Remote',
                    'is_remote'   => 1, // Everything on WWR is remote
                    'salary_text' => $salaryText,
                    'salary_min'  => $salaryMin,
                    'salary_max'  => $salaryMax,
                    'description' => $descClean,
                    'posted_at'   => $postedAt,
                ]);
            }
        }

        return $listings;
    }
}


// ─────────────────────────────────────────────────────────────────────
//  3. Craigslist Buffalo — RSS feed
// ─────────────────────────────────────────────────────────────────────

class CraigslistScraper implements JobScraper
{
    use FeedScraperHelpers;

    private const BASE_URL = 'https://buffalo.craigslist.org/search/jjj';

    public function source(): string { return 'craigslist'; }

    public function fetch(array $track): array
    {
        $keywords = $track['role_keywords'] ?? '';
        if (!$keywords) return [];

        // Build RSS URL with search keywords
        $params = [
            'format' => 'rss',
            'query'  => $keywords,
        ];

        // Craigslist uses is_telecommuting=1 for remote jobs
        if (!empty($track['remote_ok'])) {
            $params['is_telecommuting'] = '1';
        }

        $url = self::BASE_URL . '?' . http_build_query($params);

        $xml = $this->curlGet($url);
        if (!$xml) return [];

        $feed = $this->parseRss($xml);
        if (!$feed) return [];

        // Craigslist RSS uses RDF format with rss:item or item elements
        // Register namespaces
        $namespaces = $feed->getNamespaces(true);

        $items = [];
        // Try standard RSS 2.0 channel/item
        if (isset($feed->channel->item)) {
            foreach ($feed->channel->item as $item) {
                $items[] = $item;
            }
        }
        // Try RDF format (items at root level)
        if (empty($items) && isset($feed->item)) {
            foreach ($feed->item as $item) {
                $items[] = $item;
            }
        }
        // Try with rdf namespace
        if (empty($items)) {
            foreach ($feed->children() as $child) {
                if ($child->getName() === 'item') {
                    $items[] = $child;
                }
            }
        }

        $listings = [];
        foreach ($items as $item) {
            $title   = (string)($item->title ?? '');
            $link    = (string)($item->link ?? '');
            $desc    = (string)($item->description ?? '');
            $pubDate = (string)($item->pubDate ?? ($item->date ?? ''));

            // dc:date namespace fallback
            if (!$pubDate && isset($namespaces['dc'])) {
                $dc = $item->children($namespaces['dc']);
                $pubDate = (string)($dc->date ?? '');
            }

            if (!$title || !$link) continue;

            $descClean = $this->cleanText($desc);
            if (strlen($descClean) > 1000) {
                $descClean = substr($descClean, 0, 1000);
            }

            // Exclude check
            if ($this->shouldExclude($title, $descClean, $track)) continue;

            // Extract source ID from Craigslist URL (numeric post ID)
            $sourceId = '';
            if (preg_match('#/(\d{8,})\.html#', $link, $m)) {
                $sourceId = $m[1];
            }

            // Check for remote indicators
            $isRemote = 0;
            $haystack = strtolower($title . ' ' . $descClean);
            if (str_contains($haystack, 'remote') || str_contains($haystack, 'telecommut') || str_contains($haystack, 'work from home')) {
                $isRemote = 1;
            }

            // Salary extraction
            $salaryText = null;
            $salaryMin  = null;
            $salaryMax  = null;
            if (preg_match('/\$[\d,]+(?:\s*[-–—to]+\s*\$?[\d,]+)?(?:\s*(?:\/yr|\/year|per year|annually|\/hr|\/hour|per hour|[kK]))?/', $title . ' ' . $descClean, $sm)) {
                $salaryText = $sm[0];
                [$salaryMin, $salaryMax] = $this->extractSalary($salaryText);
            }

            // Posted date
            $postedAt = null;
            if ($pubDate) {
                try {
                    $dt = new DateTime($pubDate);
                    $postedAt = $dt->format('Y-m-d H:i:s');
                } catch (Throwable $e) {}
            }

            // Craigslist posts in the Buffalo feed are local by default
            $location = 'Buffalo, NY';
            // Some posts include a location in the title like "(Amherst)"
            if (preg_match('/\(([^)]+)\)\s*$/', $title, $locMatch)) {
                $location = trim($locMatch[1]) . ', NY';
                $title = trim(preg_replace('/\s*\([^)]+\)\s*$/', '', $title));
            }

            $listings[] = $this->listing([
                'source_url'  => $link,
                'source_id'   => $sourceId,
                'title'       => $title,
                'company'     => '(unknown)', // Craigslist rarely includes company
                'location'    => $location,
                'is_remote'   => $isRemote,
                'salary_text' => $salaryText,
                'salary_min'  => $salaryMin,
                'salary_max'  => $salaryMax,
                'description' => $descClean,
                'posted_at'   => $postedAt,
            ]);
        }

        return $listings;
    }
}


// ─────────────────────────────────────────────────────────────────────
//  4. Upwork — HTML file parser (saved search pages)
// ─────────────────────────────────────────────────────────────────────

class UpworkScraper implements JobScraper
{
    use FeedScraperHelpers;

    public function source(): string { return 'upwork'; }

    /**
     * Parse saved Upwork HTML files from public/uploads/.
     * User saves search results via Ctrl+S → "Webpage, Complete" and
     * drops the .html file into public/uploads/.
     */
    public function fetch(array $track): array
    {
        $uploadDir = BASE_PATH . '/public/uploads';
        $files = glob($uploadDir . '/Upwork*.html');
        if (!$files) {
            $files = glob($uploadDir . '/upwork*.html');
        }
        if (!$files) return [];

        $keywords = strtolower($track['role_keywords'] ?? '');
        $keywordList = array_filter(array_map('trim', preg_split('/[,;|]+/', $keywords)));

        $allListings = [];

        foreach ($files as $file) {
            $listings = $this->parseFile($file, $track, $keywordList);
            $allListings = array_merge($allListings, $listings);
        }

        return $allListings;
    }

    private function parseFile(string $file, array $track, array $keywordList): array
    {
        $html = file_get_contents($file);
        if (!$html) return [];

        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR);
        $xpath = new \DOMXPath($dom);

        $sections = $xpath->query('//*[@data-test="job-tile-list"]/section');
        if (!$sections->length) return [];

        $listings = [];

        for ($i = 0; $i < $sections->length; $i++) {
            $section = $sections->item($i);

            // Title + URL from h3.job-tile-title > a
            $titleA = $xpath->query('.//h3[contains(@class,"job-tile-title")]//a', $section);
            if (!$titleA->length) continue;
            $title = trim($titleA->item(0)->textContent);
            $url   = $titleA->item(0)->getAttribute('href');

            if ($url && !str_starts_with($url, 'http')) {
                $url = 'https://www.upwork.com' . $url;
            }

            $sourceId = '';
            if (preg_match('/~(\d+)/', $url, $m)) {
                $sourceId = $m[1];
            }

            // Description from p.mb-0
            $descEl = $xpath->query('.//p[contains(@class,"mb-0")]', $section);
            $description = '';
            for ($d = 0; $d < $descEl->length; $d++) {
                $text = trim($descEl->item($d)->textContent);
                if (str_contains($text, 'paid to post')) continue;
                if (strlen($text) > strlen($description)) {
                    $description = $text;
                }
            }
            if (!$description) {
                $descEl2 = $xpath->query('.//*[contains(@class,"text-body-sm")]', $section);
                for ($d = 0; $d < $descEl2->length; $d++) {
                    $text = trim($descEl2->item($d)->textContent);
                    if (strlen($text) > strlen($description)) {
                        $description = $text;
                    }
                }
            }
            if (strlen($description) > 1000) {
                $description = substr($description, 0, 1000);
            }

            // Rate/budget from small elements
            $salaryText = null;
            $salaryMin  = null;
            $salaryMax  = null;
            $smalls = $xpath->query('.//small', $section);
            for ($s = 0; $s < $smalls->length; $s++) {
                $text = trim($smalls->item($s)->textContent);
                if (preg_match('/^(Hourly|Fixed)[:\s]*\$[\d,\.]+/i', $text)) {
                    if (preg_match('/((?:Hourly|Fixed)[:\s]*\$[\d,\.]+(?:\s*[-–—]\s*\$[\d,\.]+)?)/i', $text, $rm)) {
                        $salaryText = trim($rm[1]);
                    }
                    [$salaryMin, $salaryMax] = $this->extractSalary($text);
                    break;
                }
            }

            // Client location
            $location = 'Remote';
            for ($s = 0; $s < $smalls->length; $s++) {
                $text = trim($smalls->item($s)->textContent);
                if (preg_match('/^\s*(United States|Canada|United Kingdom|Australia|Germany|India|Remote)/i', $text)) {
                    $location = trim($text);
                    break;
                }
            }

            // Posted time
            $postedEl = $xpath->query('.//*[@data-test="posted-on"]', $section);
            $postedText = $postedEl->length ? trim($postedEl->item(0)->textContent) : '';
            $postedAt = $this->parseRelativeDate($postedText);

            // Skills
            $skills = [];
            $skillEls = $xpath->query('.//a[contains(@class,"air3-token")]', $section);
            for ($s = 0; $s < $skillEls->length; $s++) {
                $sk = trim($skillEls->item($s)->textContent);
                if ($sk) $skills[] = $sk;
            }
            if ($skills) {
                $description .= ' | Skills: ' . implode(', ', $skills);
            }

            // Keyword filtering
            if ($keywordList) {
                $haystack = strtolower($title . ' ' . $description);
                $match = false;
                foreach ($keywordList as $kw) {
                    if (str_contains($haystack, $kw)) { $match = true; break; }
                }
                if (!$match) continue;
            }

            if ($this->shouldExclude($title, $description, $track)) continue;

            $listings[] = $this->listing([
                'source_url'  => $url,
                'source_id'   => $sourceId,
                'title'       => $title,
                'company'     => 'Upwork Client',
                'location'    => $location,
                'is_remote'   => 1,
                'salary_text' => $salaryText,
                'salary_min'  => $salaryMin,
                'salary_max'  => $salaryMax,
                'description' => $description,
                'posted_at'   => $postedAt,
            ]);
        }

        return $listings;
    }

    private function parseRelativeDate(string $text): ?string
    {
        $text = strtolower(trim($text));
        if (!$text) return null;

        $now = new \DateTime();

        if (str_contains($text, 'just now') || str_contains($text, 'moment')) {
            return $now->format('Y-m-d H:i:s');
        }
        if (preg_match('/(\d+)\s*minute/', $text, $m)) {
            $now->modify("-{$m[1]} minutes");
            return $now->format('Y-m-d H:i:s');
        }
        if (preg_match('/(\d+)\s*hour/', $text, $m)) {
            $now->modify("-{$m[1]} hours");
            return $now->format('Y-m-d H:i:s');
        }
        if (str_contains($text, 'yesterday')) {
            $now->modify('-1 day');
            return $now->format('Y-m-d H:i:s');
        }
        if (preg_match('/(\d+)\s*day/', $text, $m)) {
            $now->modify("-{$m[1]} days");
            return $now->format('Y-m-d H:i:s');
        }
        if (preg_match('/(\d+)\s*week/', $text, $m)) {
            $now->modify("-{$m[1]} weeks");
            return $now->format('Y-m-d H:i:s');
        }

        return null;
    }
}
