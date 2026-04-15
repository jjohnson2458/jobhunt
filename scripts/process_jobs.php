<?php
/**
 * Job posting processor pipeline.
 *
 *   php scripts/process_jobs.php
 *
 * 1. Moves any new Indeed .html files from Downloads → jobs/
 * 2. Parses each new file in jobs/
 * 3. Scores against tracks, checks blacklist + already-applied
 * 4. If score >= threshold: generates cover letter + resume, saves to applications/{slug}/
 * 5. Emails a report
 */
define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function ($c) {
    foreach ([BASE_PATH . "/core/$c.php", BASE_PATH . "/app/Models/$c.php", BASE_PATH . "/app/Services/$c.php"] as $p) {
        if (file_exists($p)) { require_once $p; return; }
    }
});
Env::load(BASE_PATH);

$downloadsDir  = 'C:/Users/email/Downloads';
$jobsDir       = BASE_PATH . '/jobs';
$appsDir       = BASE_PATH . '/applications';
$scoreThreshold = 55; // minimum score to generate materials

// ── Step 1: Move new Indeed HTML files from Downloads → jobs/ ──
$moved = [];
foreach (glob("$downloadsDir/*Indeed*.html") as $file) {
    $dest = $jobsDir . '/' . basename($file);
    if (!file_exists($dest)) {
        rename($file, $dest);
        // Also move the _files folder if it exists (saved page assets)
        $filesDir = preg_replace('/\.html$/', '_files', $file);
        if (is_dir($filesDir)) {
            $destFiles = preg_replace('/\.html$/', '_files', $dest);
            rename($filesDir, $destFiles);
        }
        $moved[] = basename($file);
        echo "[move] " . basename($file) . " → jobs/\n";
    }
}
if (!$moved) {
    echo "[move] No new Indeed files in Downloads.\n";
}

// ── Step 1b: Process pending submissions (from web /submit form) ──
foreach (glob("$jobsDir/pending_*.json") as $pf) {
    $entry = json_decode(file_get_contents($pf), true);
    if (!$entry || !empty($entry['processed'])) continue;

    // If URL-only, try to fetch and parse it
    if (!empty($entry['url']) && empty($entry['text'])) {
        echo "[submit] Fetching URL: {$entry['url']}\n";
        $fetcher = new UrlFetcher();
        $fetched = $fetcher->fetch($entry['url']);
        if ($fetched) {
            echo "[submit] Fetched: {$fetched['title']} at {$fetched['company']}\n";
            // Save as .txt for the main pipeline to pick up
            $title = $entry['title'] ?: $fetched['title'];
            $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(substr($title, 0, 60)));
            $stamp = date('Ymd_His');
            $txtFile = "$jobsDir/submitted_{$slug}_{$stamp}.txt";
            $header = "Title: {$fetched['title']}\nCompany: {$fetched['company']}\nLocation: {$fetched['location']}\nSalary: " . ($fetched['salary_text'] ?? 'n/a') . "\nURL: {$entry['url']}\nSource: {$fetched['source']}\nSubmitted: " . date('Y-m-d H:i:s') . " (fetched from URL)\n" . str_repeat('-', 60) . "\n\n" . ($fetched['apply_info'] ? $fetched['apply_info'] . "\n\n" : '') . $fetched['description'];
            file_put_contents($txtFile, $header);
        } else {
            echo "[submit] Could not fetch URL (Cloudflare?) — save page to Downloads instead.\n";
        }
    } elseif (!empty($entry['text'])) {
        echo "[submit] Pasted submission: " . ($entry['title'] ?: basename($pf)) . "\n";
    }
    $entry['processed'] = true;
    file_put_contents($pf, json_encode($entry, JSON_PRETTY_PRINT));
}

// ── Step 2: Find unprocessed files in jobs/ ──
$parser    = new IndeedPageParser();
$listings  = new Listing();
$scorer    = new ListingScorer();
$blacklist = new Blacklist();
$appModel  = new Application();
$tracks    = (new JobTrack())->active();

// Track which files have already been processed (by checking DB source_id or a marker file)
$processedLog = $jobsDir . '/.processed.json';
$processed = file_exists($processedLog) ? json_decode(file_get_contents($processedLog), true) : [];

$results = [];

// Process submitted .txt files (from phone/web form)
foreach (glob("$jobsDir/submitted_*.txt") as $file) {
    $base = basename($file);
    if (in_array($base, $processed)) continue;

    echo "[parse-text] $base\n";
    $raw = file_get_contents($file);

    // Parse header lines
    $title = ''; $url = ''; $company = ''; $location = ''; $salaryText = '';
    if (preg_match('/^Title:\s*(.+)/mi', $raw, $m))    $title    = trim($m[1]);
    if (preg_match('/^URL:\s*(.+)/mi', $raw, $m))      $url      = trim($m[1]);
    if (preg_match('/^Company:\s*(.+)/mi', $raw, $m))   $company  = trim($m[1]);
    if (preg_match('/^Location:\s*(.+)/mi', $raw, $m))  $location = trim($m[1]);
    if (preg_match('/^Salary:\s*(.+)/mi', $raw, $m))    $salaryText = trim($m[1]);

    // Everything after the dashes is the description
    $parts = preg_split('/^-{10,}$/m', $raw, 2);
    $description = trim($parts[1] ?? $raw);

    // Fallback heuristics if header fields were empty
    if (!$company && preg_match('/(?:company|employer|at)\s*[:]\s*(.+)/i', $description, $m)) $company = trim($m[1]);
    if (!$location && preg_match('/(?:location|city)\s*[:]\s*(.+)/i', $description, $m)) $location = trim($m[1]);
    if ((!$salaryText || $salaryText === 'n/a') && preg_match('/\$[\d,]+(?:\.\d+)?(?:\s*[-–]\s*\$?[\d,]+(?:\.\d+)?)?(?:\s*(?:per|a|\/)\s*(?:hour|year|yr|hr))?/i', $description, $m)) $salaryText = $m[0];
    if ($salaryText === 'n/a') $salaryText = '';

    $data = [
        'source'      => $url ? 'indeed' : 'manual',
        'source_url'  => $url ?: null,
        'title'       => $title ?: '(untitled)',
        'company'     => $company ?: '(unknown)',
        'location'    => $location,
        'is_remote'   => stripos($description, 'remote') !== false ? 1 : 0,
        'salary_text' => $salaryText ?: null,
        'salary_min'  => null,
        'salary_max'  => null,
        'description' => $description,
        'posted_at'   => null,
    ];

    // Score, upsert, generate — same as HTML pipeline below
    $bestScore = 0; $bestTrack = null; $bestReason = '';
    foreach ($tracks as $t) {
        $sc = $scorer->score($data, $t);
        if ($sc['score'] > $bestScore) { $bestScore = $sc['score']; $bestReason = $sc['reason']; $bestTrack = $t; }
    }
    $data['score'] = $bestScore; $data['score_reason'] = $bestReason; $data['track_id'] = $bestTrack['id'] ?? null;
    $res = $listings->upsert($data);
    $db = Database::getInstance();
    $db->prepare("UPDATE listings SET score=?, score_reason=?, description=?, track_id=? WHERE id=?")->execute([$bestScore, $bestReason, $data['description'], $data['track_id'], $res['id']]);

    $result = ['file'=>$base, 'title'=>$data['title'], 'company'=>$data['company'], 'location'=>$data['location'], 'salary'=>$data['salary_text']??'', 'score'=>$bestScore, 'reason'=>$bestReason, 'track'=>$bestTrack['name']??'none', 'blacklisted'=>false, 'already_applied'=>false, 'listing_id'=>$res['id'], 'apply_info'=>'', 'worthy'=>false, 'cover_letter'=>null, 'resume'=>null];

    if ($bestScore >= $scoreThreshold) {
        $result['worthy'] = true;
        $rawSlug = strip_tags(html_entity_decode($data['company'].'-'.$data['title'], ENT_QUOTES, 'UTF-8'));
        $slug = trim(substr(strtolower(preg_replace('/[^a-z0-9]+/i','-',$rawSlug)),0,80),'-');
        $appDir = "$appsDir/$slug";
        if (!is_dir($appDir)) mkdir($appDir, 0775, true);
        file_put_contents("$appDir/job_summary.txt", "Title: {$data['title']}\nCompany: {$data['company']}\nLocation: {$data['location']}\nSalary: ".($data['salary_text']??'n/a')."\nScore: $bestScore ($bestReason)\nTrack: ".($bestTrack['name']??'')."\n\n--- FULL DESCRIPTION ---\n{$data['description']}");
        try {
            $gen = new CoverLetterGenerator();
            $genResult = $gen->generate($data, $bestTrack);
            if ($genResult['cover_letter_text']) file_put_contents("$appDir/cover_letter.txt", $genResult['cover_letter_text']);
            if ($genResult['resume_path'] && file_exists(BASE_PATH.'/'.$genResult['resume_path'])) {
                $ext = pathinfo($genResult['resume_path'], PATHINFO_EXTENSION);
                copy(BASE_PATH.'/'.$genResult['resume_path'], "$appDir/resume_tailored.$ext");
            }
            $result['cover_letter'] = $genResult['cover_letter_text'] ?? null;
            echo "[generate] $slug — saved to applications/$slug/\n";
        } catch (Throwable $e) { echo "[generate] FAILED: ".$e->getMessage()."\n"; }
    }
    $processed[] = $base;
    $results[] = $result;
}

// Process Indeed .html files
foreach (glob("$jobsDir/*.html") as $file) {
    $base = basename($file);
    if (in_array($base, $processed)) {
        echo "[skip] $base (already processed)\n";
        continue;
    }

    echo "[parse] $base\n";
    $data = $parser->parse($file);

    // ── Step 3: Score and evaluate ──
    $bestScore = 0; $bestTrack = null; $bestReason = '';
    foreach ($tracks as $t) {
        $sc = $scorer->score($data, $t);
        if ($sc['score'] > $bestScore) {
            $bestScore  = $sc['score'];
            $bestReason = $sc['reason'];
            $bestTrack  = $t;
        }
    }
    $data['score']        = $bestScore;
    $data['score_reason'] = $bestReason;
    $data['track_id']     = $bestTrack['id'] ?? null;

    // Check blacklist
    $bl = $blacklist->matches($data['company'], $data['title'], $data['description'] ?? '', (int)($data['track_id'] ?? 0));
    if ($bl) { $data['status'] = 'blacklisted'; }

    // Check already applied
    $alreadyApplied = $appModel->alreadyApplied($data['company'], $data['title']);

    // Upsert into DB (updates score if already exists from email alert)
    $applyInfo = $data['apply_info'] ?? '';
    unset($data['apply_info'], $data['filename']);
    $res = $listings->upsert($data);
    $listingId = $res['id'];

    // Update score + description even if listing already existed
    $db = Database::getInstance();
    $db->prepare("UPDATE listings SET score=?, score_reason=?, description=?, track_id=? WHERE id=?")
       ->execute([$bestScore, $bestReason, $data['description'], $data['track_id'], $listingId]);

    $result = [
        'file'      => $base,
        'title'     => $data['title'],
        'company'   => $data['company'],
        'location'  => $data['location'],
        'salary'    => $data['salary_text'] ?? '',
        'score'     => $bestScore,
        'reason'    => $bestReason,
        'track'     => $bestTrack['name'] ?? 'none',
        'blacklisted' => isset($data['status']) && $data['status'] === 'blacklisted',
        'already_applied' => $alreadyApplied,
        'listing_id' => $listingId,
        'apply_info' => $applyInfo,
        'worthy'    => false,
        'cover_letter' => null,
        'resume'    => null,
    ];

    // ── Step 4: If worthy, generate materials ──
    if ($bestScore >= $scoreThreshold && !$alreadyApplied && empty($data['status'])) {
        $result['worthy'] = true;
        $rawSlug = strip_tags(html_entity_decode($data['company'] . '-' . $data['title'], ENT_QUOTES, 'UTF-8'));
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $rawSlug));
        $slug = trim(substr($slug, 0, 80), '-');
        $appDir = "$appsDir/$slug";
        if (!is_dir($appDir)) { mkdir($appDir, 0775, true); }

        // Save job summary
        $summary = "Title: {$data['title']}\n"
            . "Company: {$data['company']}\n"
            . "Location: {$data['location']}\n"
            . "Salary: " . ($data['salary_text'] ?? 'n/a') . "\n"
            . "Score: {$bestScore} ({$bestReason})\n"
            . "Track: " . ($bestTrack['name'] ?? '') . "\n"
            . ($applyInfo ? "\n--- HOW TO APPLY ---\n$applyInfo" : '')
            . "\n--- FULL DESCRIPTION ---\n" . $data['description'];
        file_put_contents("$appDir/job_summary.txt", $summary);

        // Generate cover letter + resume
        try {
            $gen = new CoverLetterGenerator();
            $genResult = $gen->generate($data, $bestTrack);
            $result['cover_letter'] = $genResult['cover_letter_text'] ?? null;

            // Save cover letter to app dir
            if ($genResult['cover_letter_text']) {
                file_put_contents("$appDir/cover_letter.txt", $genResult['cover_letter_text']);
            }
            if ($genResult['cover_letter_path'] && file_exists(BASE_PATH . '/' . $genResult['cover_letter_path'])) {
                copy(BASE_PATH . '/' . $genResult['cover_letter_path'], "$appDir/cover_letter_generated.txt");
            }
            if ($genResult['resume_path'] && file_exists(BASE_PATH . '/' . $genResult['resume_path'])) {
                $ext = pathinfo($genResult['resume_path'], PATHINFO_EXTENSION);
                copy(BASE_PATH . '/' . $genResult['resume_path'], "$appDir/resume_tailored.$ext");
                $result['resume'] = "resume_tailored.$ext";
            }

            // Save application record
            $existing = $appModel->forListing($listingId);
            $payload = [
                'listing_id'        => $listingId,
                'status'            => 'drafted',
                'cover_letter_text' => $genResult['cover_letter_text'] ?? null,
                'cover_letter_path' => $genResult['cover_letter_path'] ?? null,
                'resume_path'       => $genResult['resume_path']       ?? null,
                'notes'             => $applyInfo,
            ];
            if (!$existing) {
                $appModel->create($payload);
            }

            echo "[generate] $slug — cover letter + resume saved to applications/$slug/\n";
        } catch (Throwable $e) {
            echo "[generate] FAILED for $slug: " . $e->getMessage() . "\n";
            $result['cover_letter'] = 'FAILED: ' . $e->getMessage();
        }
    } else {
        $reason = [];
        if ($bestScore < $scoreThreshold) $reason[] = "score $bestScore < $scoreThreshold";
        if ($alreadyApplied) $reason[] = "already applied";
        if (!empty($data['status'])) $reason[] = $data['status'];
        echo "[skip-gen] {$data['company']} — {$data['title']} (" . implode(', ', $reason) . ")\n";
    }

    $processed[] = $base;
    $results[] = $result;
}

// Save processed log
file_put_contents($processedLog, json_encode($processed, JSON_PRETTY_PRINT));

// ── Step 5: Email report ──
if ($results) {
    $worthy  = array_filter($results, fn($r) => $r['worthy']);
    $skipped = array_filter($results, fn($r) => !$r['worthy']);

    $body = "<h2>Job Posting Processor Report</h2>";
    $body .= "<p>" . count($results) . " files processed, <strong>" . count($worthy) . " worth pursuing</strong>, " . count($skipped) . " skipped.</p>";

    if ($worthy) {
        $body .= "<h3 style='color:#5a6b3a;'>Worth Pursuing</h3><table style='border-collapse:collapse;width:100%;font-size:13px;'>";
        $body .= "<tr style='background:#6b4f2b;color:#faf6ee;'><th style='padding:6px;'>Score</th><th style='padding:6px;'>Title</th><th style='padding:6px;'>Company</th><th style='padding:6px;'>Location</th><th style='padding:6px;'>Salary</th><th style='padding:6px;'>Track</th></tr>";
        foreach ($worthy as $r) {
            $body .= "<tr><td style='padding:6px;'><strong>{$r['score']}</strong></td><td style='padding:6px;'>{$r['title']}</td><td style='padding:6px;'>{$r['company']}</td><td style='padding:6px;'>{$r['location']}</td><td style='padding:6px;'>{$r['salary']}</td><td style='padding:6px;'>{$r['track']}</td></tr>";
            // Apply method row
            $applyHtml = '';
            if ($r['apply_info'] && preg_match('/Email:\s*(\S+)/i', $r['apply_info'], $em)) {
                $applyHtml = "<a href='mailto:{$em[1]}'>Email: {$em[1]}</a>";
                if (preg_match('/Deadline:\s*(.+)/i', $r['apply_info'], $dl)) $applyHtml .= " | <span style='color:red;'>Deadline: {$dl[1]}</span>";
            }
            if (!$applyHtml) {
                // Link to app folder on web
                $slug = trim(substr(strtolower(preg_replace('/[^a-z0-9]+/i','-', strip_tags($r['company'].'-'.$r['title']))),0,80),'-');
                $applyHtml = "<a href='http://jobhunt.local/applications/$slug'>View application materials</a>";
            }
            $body .= "<tr><td colspan='6' style='padding:4px 6px;font-size:12px;color:#6b4f2b;'>$applyHtml</td></tr>";
        }
        $body .= "</table>";
        $body .= "<p>Cover letters and resumes saved to <code>applications/</code> folder.</p>";
    }

    if ($skipped) {
        $body .= "<h3 style='color:#a0522d;'>Skipped</h3><ul style='font-size:13px;'>";
        foreach ($skipped as $r) {
            $reasons = [];
            if ($r['score'] < $scoreThreshold) $reasons[] = "score {$r['score']}";
            if ($r['blacklisted']) $reasons[] = "blacklisted";
            if ($r['already_applied']) $reasons[] = "already applied";
            $body .= "<li>{$r['title']} at {$r['company']} — " . implode(', ', $reasons) . "</li>";
        }
        $body .= "</ul>";
    }

    // Send email — keep body under 7KB to avoid Windows shell limits
    $subjectLine = count($worthy) . " job" . (count($worthy) !== 1 ? 's' : '') . " worth pursuing from " . count($results) . " processed";
    // Truncate body if needed
    if (strlen($body) > 7000) {
        $body = substr($body, 0, 6900) . '...</table><p><em>(truncated — see applications/ folder for full details)</em></p>';
    }
    $cmd = 'php C:/xampp/htdocs/claude_messenger/notify.php'
         . ' -s ' . escapeshellarg("Jobhunt: $subjectLine")
         . ' -b ' . escapeshellarg($body)
         . ' -p claude_jobhunt';
    shell_exec($cmd);
    echo "\n[email] Report sent.\n";
} else {
    echo "\nNo new files to process.\n";
}

echo "Done.\n";
