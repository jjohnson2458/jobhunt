<?php
/**
 * Fetches and parses job postings from URLs.
 * Supports Greenhouse, Lever, and generic pages.
 * Returns normalized listing data like IndeedPageParser.
 */
class UrlFetcher
{
    /**
     * Fetch and parse a job posting URL.
     * Returns null if the URL can't be fetched or parsed.
     */
    public function fetch(string $url): ?array
    {
        // Route to the right parser
        if (preg_match('/greenhouse\.io|gh_jid=(\d+)/i', $url)) {
            return $this->fetchGreenhouse($url);
        }
        if (preg_match('/lever\.co/i', $url)) {
            return $this->fetchLever($url);
        }
        // Generic: try a simple curl fetch (works for non-CF sites)
        return $this->fetchGeneric($url);
    }

    /**
     * Greenhouse — use their public JSON API.
     * URL formats:
     *   https://boards.greenhouse.io/company/jobs/123
     *   https://example.com/jobs/?gh_jid=123
     *   https://example.com/jobs/?gh_jid=123&gh_src=xxx
     */
    private function fetchGreenhouse(string $url): ?array
    {
        $jobId = null;
        $boardToken = null;

        // Extract job ID
        if (preg_match('/gh_jid=(\d+)/', $url, $m)) {
            $jobId = $m[1];
        } elseif (preg_match('#greenhouse\.io/[^/]+/jobs/(\d+)#', $url, $m)) {
            $jobId = $m[1];
        }
        if (!$jobId) return null;

        // Extract board token — may need to fetch the page to find "for=TOKEN"
        if (preg_match('#boards\.greenhouse\.io/([^/]+)#', $url, $m)) {
            $boardToken = $m[1];
        } else {
            // Fetch the host page to find the embed token
            $html = $this->curlGet($url);
            if ($html && preg_match('/for=([a-z0-9]+)/i', $html, $m)) {
                $boardToken = $m[1];
            }
        }
        if (!$boardToken) return null;

        // Call Greenhouse API
        $apiUrl = "https://boards-api.greenhouse.io/v1/boards/$boardToken/jobs/$jobId";
        $json = $this->curlGet($apiUrl);
        if (!$json) return null;
        $data = json_decode($json, true);
        if (!$data || isset($data['status'])) return null;

        // Parse content (double-encoded HTML)
        $content = $data['content'] ?? '';
        $content = html_entity_decode(html_entity_decode($content, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        $description = strip_tags(str_replace(['<br>', '<br/>', '</p>', '</li>', '</div>'], "\n", $content));
        $description = html_entity_decode(trim(preg_replace('/\n{3,}/', "\n\n", $description)), ENT_QUOTES, 'UTF-8');

        // Salary from metadata
        $salaryText = null;
        foreach ($data['metadata'] ?? [] as $meta) {
            if (stripos($meta['name'] ?? '', 'pay') !== false || stripos($meta['name'] ?? '', 'salary') !== false) {
                $val = $meta['value'] ?? '';
                if (is_array($val)) {
                    $min = $val['min_value'] ?? 0;
                    $max = $val['max_value'] ?? 0;
                    if ($max > 0) $salaryText = "\$$min - \$$max";
                } elseif (is_string($val)) {
                    $salaryText = $val;
                }
            }
        }

        // Employment type
        $empType = '';
        foreach ($data['metadata'] ?? [] as $meta) {
            if (stripos($meta['name'] ?? '', 'employment') !== false) {
                $empType = is_string($meta['value'] ?? '') ? $meta['value'] : '';
            }
        }

        $location = $data['location']['name'] ?? '';

        return [
            'source'      => 'greenhouse',
            'source_url'  => $data['absolute_url'] ?? $url,
            'source_id'   => (string)$jobId,
            'title'       => $data['title'] ?? '(untitled)',
            'company'     => $data['company_name'] ?? '(unknown)',
            'location'    => $location,
            'is_remote'   => stripos($location, 'remote') !== false ? 1 : 0,
            'salary_text' => $salaryText,
            'salary_min'  => null,
            'salary_max'  => null,
            'description' => $description,
            'posted_at'   => $data['first_published'] ?? null,
            'apply_info'  => "Apply at: " . ($data['absolute_url'] ?? $url) . ($empType ? "\nType: $empType" : ''),
        ];
    }

    /**
     * Lever — their public API is at /v0/postings/company/jobid
     */
    private function fetchLever(string $url): ?array
    {
        // https://jobs.lever.co/company/jobid
        if (!preg_match('#lever\.co/([^/]+)/([a-f0-9-]+)#i', $url, $m)) return null;
        $company = $m[1];
        $jobId = $m[2];
        $apiUrl = "https://api.lever.co/v0/postings/$company/$jobId";
        $json = $this->curlGet($apiUrl);
        if (!$json) return null;
        $data = json_decode($json, true);
        if (!$data || !isset($data['text'])) return null;

        $desc = $data['descriptionPlain'] ?? strip_tags($data['description'] ?? '');
        $lists = '';
        foreach ($data['lists'] ?? [] as $list) {
            $lists .= "\n\n{$list['text']}\n";
            foreach ($list['content'] ?? [] as $item) {
                $lists .= "• " . strip_tags($item) . "\n";
            }
        }

        $salaryText = null;
        if (!empty($data['salaryRange'])) {
            $sr = $data['salaryRange'];
            $salaryText = '$' . number_format($sr['min'] ?? 0) . ' - $' . number_format($sr['max'] ?? 0);
        }

        return [
            'source'      => 'lever',
            'source_url'  => $data['hostedUrl'] ?? $url,
            'source_id'   => $jobId,
            'title'       => $data['text'] ?? '(untitled)',
            'company'     => $data['categories']['team'] ?? $company,
            'location'    => $data['categories']['location'] ?? '',
            'is_remote'   => stripos($data['workplaceType'] ?? '', 'remote') !== false ? 1 : 0,
            'salary_text' => $salaryText,
            'salary_min'  => $data['salaryRange']['min'] ?? null,
            'salary_max'  => $data['salaryRange']['max'] ?? null,
            'description' => $desc . $lists,
            'posted_at'   => isset($data['createdAt']) ? date('Y-m-d H:i:s', $data['createdAt'] / 1000) : null,
            'apply_info'  => "Apply at: " . ($data['hostedUrl'] ?? $url),
        ];
    }

    /**
     * Generic URL fetch — works for sites without Cloudflare.
     * Falls back to basic HTML parsing.
     */
    private function fetchGeneric(string $url): ?array
    {
        $html = $this->curlGet($url);
        if (!$html) return null;

        // If Cloudflare blocks, bail
        if (stripos($html, 'Performing security verification') !== false
            || stripos($html, 'Just a moment') !== false) {
            return null;
        }

        // Title from <title> tag
        $title = '';
        if (preg_match('#<title[^>]*>([^<]+)</title>#i', $html, $m)) {
            $title = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }

        // Extract all paragraph text as description
        preg_match_all('#<p[^>]*>(.*?)</p>#is', $html, $ps);
        $desc = implode("\n", array_map(fn($p) => trim(strip_tags($p)), $ps[1] ?? []));

        // Company from og:site_name
        $company = '';
        if (preg_match('#property="og:site_name"\s+content="([^"]+)"#i', $html, $m)) {
            $company = $m[1];
        }

        return [
            'source'      => 'url',
            'source_url'  => $url,
            'title'       => $title ?: '(untitled)',
            'company'     => $company ?: '(unknown)',
            'location'    => '',
            'is_remote'   => stripos($desc, 'remote') !== false ? 1 : 0,
            'salary_text' => null,
            'salary_min'  => null,
            'salary_max'  => null,
            'description' => $desc,
            'posted_at'   => null,
            'apply_info'  => "Apply at: $url",
        ];
    }

    private function curlGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
        ]);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($code >= 200 && $code < 400) ? $result : null;
    }
}
