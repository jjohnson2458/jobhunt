<?php
/**
 * CLI scraper runner.
 *
 *   php scripts/scrape.php
 *   php scripts/scrape.php --track=1
 *   php scripts/scrape.php --source=indeed
 *
 * Bootstraps the framework standalone (without dispatching the router).
 */
define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function ($class) {
    $paths = [
        BASE_PATH . "/core/{$class}.php",
        BASE_PATH . "/app/Controllers/{$class}.php",
        BASE_PATH . "/app/Models/{$class}.php",
        BASE_PATH . "/app/Services/{$class}.php",
    ];
    foreach ($paths as $p) { if (file_exists($p)) { require_once $p; return; } }
});

Env::load(BASE_PATH);

// JobScraper.php defines the interface + Playwright scraper classes
require_once BASE_PATH . '/app/Services/JobScraper.php';
require_once BASE_PATH . '/app/Services/GmailScraper.php';

$opts = getopt('', ['track::', 'source::']);
$trackFilter  = isset($opts['track'])  ? (int)$opts['track']  : null;
$sourceFilter = $opts['source'] ?? null;

$tracks    = (new JobTrack())->active();
$listings  = new Listing();
$blacklist = new Blacklist();
$scorer    = new ListingScorer();
$runs      = new ScraperRun();

$allScrapers = [
    new GmailScraper(),       // primary: ingest job alert emails
    // Playwright scrapers kept as fallback for ad-hoc/manual use:
    // new IndeedScraper(),
    // new ZipRecruiterScraper(),
    // new MonsterScraper(),
    // new LinkedInScraper(),
];

foreach ($tracks as $track) {
    if ($trackFilter && (int)$track['id'] !== $trackFilter) { continue; }
    foreach ($allScrapers as $scraper) {
        if ($sourceFilter && $scraper->source() !== $sourceFilter) { continue; }
        $runId = $runs->start($scraper->source(), (int)$track['id']);
        $log = [];
        $found = 0; $new = 0; $status = 'success'; $err = null;
        try {
            $log[] = "Fetching {$scraper->source()} for track '{$track['name']}'";
            $rows = $scraper->fetch($track);
            $found = count($rows);
            $log[] = "Got $found raw rows";
            foreach ($rows as $row) {
                $row['track_id'] = (int)$track['id'];

                $bl = $blacklist->matches($row['company'], $row['title'], $row['description'] ?? '', (int)$track['id']);
                if ($bl) {
                    $log[] = "Blacklisted ({$bl['type']}: {$bl['pattern']}): {$row['company']} — {$row['title']}";
                    $row['status'] = 'blacklisted';
                }

                $sc = $scorer->score($row, $track);
                $row['score']        = $sc['score'];
                $row['score_reason'] = $sc['reason'];

                $res = $listings->upsert($row);
                if ($res['isNew']) { $new++; }
            }
        } catch (Throwable $e) {
            $status = 'failed';
            $err    = $e->getMessage();
            $log[]  = "ERROR: " . $e->getMessage();
        }
        $runs->finish($runId, $status, $found, $new, $err, implode("\n", $log));
        echo "[{$scraper->source()}] track={$track['name']} found=$found new=$new status=$status\n";
    }
}

echo "Done.\n";
