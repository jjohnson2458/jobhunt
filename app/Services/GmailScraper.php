<?php
/**
 * Gmail-based job alert ingester.
 *
 * Connects to a Gmail mailbox via IMAP, finds job alert emails from the
 * usual senders (Indeed, ZipRecruiter, LinkedIn, Monster, Dice, etc.),
 * parses each one with a sender-specific extractor, and returns a flat
 * list of normalized listings — same shape that the Playwright scrapers
 * return, so the rest of the pipeline (dedupe, score, store) is unchanged.
 *
 * Why email instead of scraping? Cloudflare blocks headless browsers on
 * all the major boards. Job alert emails come from the boards themselves
 * and are not bot-walled. As long as the user has subscribed to alerts,
 * the data lands in the inbox automatically.
 *
 * Each subclass of GmailJobParser handles ONE sender. To add a new board,
 * register a new parser in the $parsers array of GmailScraper::fetch().
 */
class GmailScraper implements JobScraper
{
    public function source(): string { return 'gmail'; }

    /**
     * Fetch listings from job alert emails.
     *
     * The $track parameter is mostly ignored — we ingest ALL alerts in
     * the mailbox and let the downstream scorer filter per track. (One
     * email batch typically covers all of a user's saved searches.)
     */
    public function fetch(array $track): array
    {
        $cfg = require BASE_PATH . '/config/app.php';
        $email = $cfg['gmail_address'] ?? '';
        $pass  = str_replace(' ', '', $cfg['gmail_password'] ?? '');
        $box   = $cfg['gmail_folder']   ?? 'INBOX';
        if (!$email || !$pass) {
            throw new RuntimeException('GMAIL_ADDRESS / GMAIL_APP_PASSWORD not configured in .env');
        }
        if (!function_exists('imap_open')) {
            throw new RuntimeException('PHP imap extension is not enabled');
        }

        $mailbox = '{imap.gmail.com:993/imap/ssl}' . $box;
        $imap = @imap_open($mailbox, $email, $pass);
        if (!$imap) {
            throw new RuntimeException('IMAP connect failed: ' . imap_last_error());
        }

        // First: check for self-shared job postings (subject starts with "jobhunt:")
        $this->processSharedEmails($imap);

        $parsers = [
            new IndeedAlertParser(),
            new ZipRecruiterAlertParser(),
            new LinkedInAlertParser(),
            new MonsterAlertParser(),
        ];

        $listings = [];
        foreach ($parsers as $parser) {
            $criteria = 'UNSEEN FROM "' . $parser->fromMatch() . '"';
            $uids = imap_search($imap, $criteria, SE_UID) ?: [];
            foreach ($uids as $uid) {
                $headers = imap_headerinfo($imap, imap_msgno($imap, $uid));
                $subject = $headers->subject ?? '';
                $body    = $this->fetchBody($imap, $uid);
                try {
                    $rows = $parser->parse($subject, $body);
                    foreach ($rows as $row) {
                        $row['source'] = $parser->boardName(); // override generic 'gmail'
                        $listings[] = $row;
                    }
                } catch (Throwable $e) {
                    error_log("[GmailScraper] {$parser->boardName()} parse error: " . $e->getMessage());
                }
                // Mark seen so we don't reparse next run
                imap_setflag_full($imap, (string)$uid, '\\Seen', ST_UID);
            }
        }

        imap_close($imap);
        return $listings;
    }

    /**
     * Fetch the HTML body of a message (preferred) or the plaintext body
     * as a fallback. Handles multipart MIME by walking the structure.
     */
    private function fetchBody($imap, int $uid): string
    {
        $msgno = imap_msgno($imap, $uid);
        $structure = imap_fetchstructure($imap, $msgno);
        $html = $this->extractPart($imap, $msgno, $structure, 'HTML');
        if ($html) return $html;
        return $this->extractPart($imap, $msgno, $structure, 'PLAIN') ?: '';
    }

    private function extractPart($imap, int $msgno, $structure, string $want, string $partNum = ''): string
    {
        if (!isset($structure->parts) || !count($structure->parts)) {
            $subtype = strtoupper($structure->subtype ?? '');
            if ($subtype === $want) {
                $data = imap_fetchbody($imap, $msgno, $partNum ?: '1');
                return $this->decodePart($data, $structure->encoding ?? 0);
            }
            return '';
        }
        foreach ($structure->parts as $i => $part) {
            $num = $partNum ? $partNum . '.' . ($i + 1) : (string)($i + 1);
            $found = $this->extractPart($imap, $msgno, $part, $want, $num);
            if ($found) return $found;
        }
        return '';
    }

    /**
     * Process emails where user shared a job posting to themselves.
     * Subject must start with "jobhunt:" — the rest is the job title.
     * Body can be a URL, pasted text, or both.
     */
    private function processSharedEmails($imap): void
    {
        // Match "jobhunt" anywhere in subject (covers "jobhunt:", "job hunt", "jobhunt indeed", etc.)
        $uids = imap_search($imap, 'UNSEEN SUBJECT "jobhunt"', SE_UID) ?: [];
        if (!$uids) return;

        foreach ($uids as $uid) {
            $msgno  = imap_msgno($imap, $uid);
            $headers = imap_headerinfo($imap, $msgno);
            $subject = $headers->subject ?? '';
            // Skip our own notification emails
            if (preg_match('/^(Jobhunt:|Nightly|claude_jobhunt)/i', $subject)
                && str_contains($headers->fromaddress ?? '', 'Claude Code')) {
                continue;
            }
            $title = trim(preg_replace('/^jobhunt:\s*/i', '', $subject));
            $body    = $this->fetchBody($imap, $uid);
            $text    = strip_tags(str_replace(['<br>', '<br/>', '</p>', '</div>'], "\n", $body));
            $text    = html_entity_decode(trim($text), ENT_QUOTES, 'UTF-8');

            // Extract URL if present
            $url = '';
            if (preg_match('#(https?://\S+indeed\.com\S*|https?://\S+ziprecruiter\S*|https?://\S+linkedin\S*|https?://\S+monster\S*)#i', $text, $m)) {
                $url = $m[1];
            }

            $stamp = date('Ymd_His');
            $slug  = $title ? preg_replace('/[^a-z0-9]+/i', '-', strtolower(substr($title, 0, 60))) : $stamp;

            // Save as submitted text file for process_jobs.php
            $txtFile = BASE_PATH . "/jobs/submitted_{$slug}_{$stamp}.txt";
            $header  = "Title: $title\n";
            if ($url) $header .= "URL: $url\n";
            $header .= "Submitted: " . date('Y-m-d H:i:s') . " (via email)\n";
            $header .= str_repeat('-', 60) . "\n\n";
            file_put_contents($txtFile, $header . $text);

            imap_setflag_full($imap, (string)$uid, '\\Seen', ST_UID);
            error_log("[GmailScraper] Shared job saved: $title → $txtFile");
        }
    }

    private function decodePart(string $data, int $encoding): string
    {
        switch ($encoding) {
            case 3: return base64_decode($data);
            case 4: return quoted_printable_decode($data);
            default: return $data;
        }
    }
}

/**
 * Base class for per-board email parsers. Each subclass declares which
 * "From:" address to match and how to turn an HTML email into listing rows.
 *
 * Returned rows use the same shape as the Playwright scrapers:
 *   source_url, source_id, title, company, location, is_remote,
 *   salary_text, salary_min, salary_max, description, posted_at
 */
abstract class GmailJobParser
{
    abstract public function boardName(): string;   // 'indeed', 'ziprecruiter', etc.
    abstract public function fromMatch(): string;   // substring for IMAP FROM filter
    abstract public function parse(string $subject, string $html): array;

    /** Strip HTML tags + collapse whitespace, useful for description fallback. */
    protected function textOf(string $html): string {
        $t = strip_tags(str_replace(['<br>','<br/>','<br />','</p>','</div>'], "\n", $html));
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $t));
    }

    /** Resolve a possibly-tracking URL down to its target ?url= or just return it. */
    protected function unwrapUrl(string $url): string {
        if (preg_match('/[?&](?:url|targetUrl|jk)=([^&]+)/i', $url, $m)) {
            return urldecode($m[1]);
        }
        return $url;
    }
}

/**
 * Indeed job alert parser.
 *
 * Indeed alert emails contain one card per job, each wrapped in a table
 * row with a link to /rc/clk?jk=XXXXX (the job key). We extract the
 * title, company, location, snippet, and salary if present.
 *
 * NOTE: Indeed changes their template every few months. Selectors here
 * are deliberately tolerant — fall back to text scraping if the structured
 * approach finds nothing.
 */
class IndeedAlertParser extends GmailJobParser
{
    public function boardName(): string { return 'indeed'; }
    public function fromMatch(): string { return 'indeed.com'; }

    public function parse(string $subject, string $html): array
    {
        $rows = [];
        $seen = [];
        // Indeed alert emails: <h2><a href="...rc/clk...jk=XXXXX">Title</a></h2>
        // followed by <td>Company</td> <td>Location</td> <td><strong>$salary</strong></td>
        if (preg_match_all('#<a[^>]+href="([^"]*rc/clk[^"]*jk=([^"&]+)[^"]*)"[^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $jk = $hit[2];
                if (isset($seen[$jk])) continue; // same job linked twice (h2 + wrapper)
                $seen[$jk] = true;
                $url   = html_entity_decode($hit[1]);
                $title = $this->textOf($hit[3]);
                if (!$title) continue;

                $company = ''; $location = ''; $salary = ''; $snippet = '';
                $tail = substr($html, strpos($html, $hit[0]) + strlen($hit[0]), 3000);

                // Indeed uses plain <td> cells for company, location, salary — no class names.
                // Extract all short <td> text values from the card block (stop at next <h2> or </a> block).
                $cardHtml = $tail;
                $nextCard = strpos($cardHtml, '<h2');
                if ($nextCard !== false) $cardHtml = substr($cardHtml, 0, $nextCard);

                preg_match_all('#<td[^>]*>([^<]{2,80})</td>#is', $cardHtml, $tds);
                $tdTexts = array_values(array_filter(array_map(function($t) {
                    $t = trim(html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8'));
                    // Skip noise: "Easily apply", "days ago", image alts
                    if (preg_match('/easily apply|days? ago|new|responsive|active/i', $t)) return '';
                    return $t;
                }, $tds[1] ?? [])));

                if (count($tdTexts) >= 1) $company  = $tdTexts[0];
                if (count($tdTexts) >= 2) $location = $tdTexts[1];

                // Salary: look for $X in a <strong> tag
                if (preg_match('#<strong[^>]*>([^<]*\$[\d,]+[^<]*)</strong>#i', $cardHtml, $sm)) {
                    $salary = trim(strip_tags($sm[1]));
                }

                // Snippet: look for description text (usually the last long <td>)
                if (preg_match('#<td[^>]*>([^<]{80,})</td>#is', $cardHtml, $snip)) {
                    $snippet = trim(html_entity_decode(strip_tags($snip[1]), ENT_QUOTES, 'UTF-8'));
                }

                $rows[] = [
                    'source_url'  => $url,
                    'source_id'   => $jk,
                    'title'       => $title,
                    'company'     => $company ?: '(unknown)',
                    'location'    => $location,
                    'is_remote'   => stripos($location . ' ' . $title, 'remote') !== false ? 1 : 0,
                    'salary_text' => $salary ?: null,
                    'description' => $snippet,
                    'posted_at'   => null,
                ];
            }
        }
        return $rows;
    }
}

/**
 * ZipRecruiter alert parser.
 * Their alert emails use a table layout with each job linked via /jobs/
 * URLs. Title is in an <a>, company/location follow on the next lines.
 */
class ZipRecruiterAlertParser extends GmailJobParser
{
    public function boardName(): string { return 'ziprecruiter'; }
    public function fromMatch(): string { return 'ziprecruiter.com'; }

    public function parse(string $subject, string $html): array
    {
        $rows = [];
        if (preg_match_all('#<a[^>]+href="([^"]*ziprecruiter\.com/[^"]*jobs?[^"]*)"[^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER)) {
            $seen = [];
            foreach ($m as $hit) {
                $url   = $this->unwrapUrl(html_entity_decode($hit[1]));
                $title = $this->textOf($hit[2]);
                if (!$title || isset($seen[$title])) continue;
                $seen[$title] = true;
                $tail = substr($html, strpos($html, $hit[0]) + strlen($hit[0]), 1500);
                $tailText = $this->textOf($tail);
                $parts = array_values(array_filter(array_map('trim', preg_split('/[·•|]/u', $tailText))));
                $company  = $parts[0] ?? '';
                $location = $parts[1] ?? '';
                $salary = '';
                if (preg_match('#\$[\d,]+(?:\.\d+)?(?:\s*-\s*\$?[\d,]+)?#', $tailText, $sm)) $salary = $sm[0];
                $rows[] = [
                    'source_url'  => $url,
                    'title'       => $title,
                    'company'     => $company ?: '(unknown)',
                    'location'    => $location,
                    'is_remote'   => stripos($tailText, 'remote') !== false ? 1 : 0,
                    'salary_text' => $salary ?: null,
                    'description' => substr($tailText, 0, 400),
                    'posted_at'   => null,
                ];
            }
        }
        return $rows;
    }
}

/**
 * LinkedIn job alert parser.
 * LinkedIn emails use /jobs/view/{id} URLs and a fairly clean structure.
 */
class LinkedInAlertParser extends GmailJobParser
{
    public function boardName(): string { return 'linkedin'; }
    public function fromMatch(): string { return 'linkedin.com'; }

    public function parse(string $subject, string $html): array
    {
        $rows = [];
        if (preg_match_all('#<a[^>]+href="([^"]*linkedin\.com/comm/jobs/view/(\d+)[^"]*)"[^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER)) {
            $seen = [];
            foreach ($m as $hit) {
                $url   = html_entity_decode($hit[1]);
                $jobId = $hit[2];
                $title = $this->textOf($hit[3]);
                if (!$title || isset($seen[$jobId])) continue;
                $seen[$jobId] = true;
                $tail = substr($html, strpos($html, $hit[0]) + strlen($hit[0]), 1200);
                $tailText = $this->textOf($tail);
                $parts = array_values(array_filter(array_map('trim', preg_split('/[·•|]/u', $tailText))));
                $rows[] = [
                    'source_url'  => $url,
                    'source_id'   => $jobId,
                    'title'       => $title,
                    'company'     => $parts[0] ?? '(unknown)',
                    'location'    => $parts[1] ?? '',
                    'is_remote'   => stripos($tailText, 'remote') !== false ? 1 : 0,
                    'salary_text' => null,
                    'description' => substr($tailText, 0, 400),
                    'posted_at'   => null,
                ];
            }
        }
        return $rows;
    }
}

/**
 * Monster alert parser. Same heuristic approach as the others.
 */
class MonsterAlertParser extends GmailJobParser
{
    public function boardName(): string { return 'monster'; }
    public function fromMatch(): string { return 'monster.com'; }

    public function parse(string $subject, string $html): array
    {
        $rows = [];
        if (preg_match_all('#<a[^>]+href="([^"]*monster\.com[^"]*job[^"]*)"[^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER)) {
            $seen = [];
            foreach ($m as $hit) {
                $url   = html_entity_decode($hit[1]);
                $title = $this->textOf($hit[2]);
                if (!$title || isset($seen[$title])) continue;
                $seen[$title] = true;
                $tail = substr($html, strpos($html, $hit[0]) + strlen($hit[0]), 1200);
                $tailText = $this->textOf($tail);
                $parts = array_values(array_filter(array_map('trim', preg_split('/[·•|]/u', $tailText))));
                $rows[] = [
                    'source_url'  => $url,
                    'title'       => $title,
                    'company'     => $parts[0] ?? '(unknown)',
                    'location'    => $parts[1] ?? '',
                    'is_remote'   => stripos($tailText, 'remote') !== false ? 1 : 0,
                    'salary_text' => null,
                    'description' => substr($tailText, 0, 400),
                    'posted_at'   => null,
                ];
            }
        }
        return $rows;
    }
}
