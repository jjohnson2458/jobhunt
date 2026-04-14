<?php
class ScraperController extends Controller {
    public function index(): void {
        $this->requireAuth();
        $this->view('runs/index', ['runs' => (new ScraperRun())->recent(100)]);
    }

    public function start(): void {
        $this->requireAuth();
        $trackId = !empty($_POST['track_id']) ? (int)$_POST['track_id'] : null;
        // Fire-and-forget: write a queue file the CLI runner picks up.
        $queueFile = BASE_PATH . '/storage/scraper_queue.txt';
        file_put_contents($queueFile, ($trackId ?? 'all') . "\n", FILE_APPEND);
        $this->flash('info', 'Scraper queued. Run scripts/scrape.php (or wait for nightly).');
        $this->redirect('/runs');
    }

    public function show(int $id): void {
        $this->requireAuth();
        $run = (new ScraperRun())->find($id);
        $this->view('runs/show', ['run' => $run]);
    }
}
