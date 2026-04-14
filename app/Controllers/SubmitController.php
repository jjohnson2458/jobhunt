<?php
/**
 * Submit a job posting from phone or any browser.
 * Accepts: URL (saved for next process_jobs run), pasted text, or email-forwarded content.
 */
class SubmitController extends Controller {
    public function form(): void {
        $this->requireAuth();
        $pending = glob(BASE_PATH . '/jobs/pending_*.json');
        $this->view('submit/form', ['pending' => count($pending)]);
    }

    public function store(): void {
        $this->requireAuth();
        $url  = trim($_POST['url'] ?? '');
        $text = trim($_POST['text'] ?? '');
        $title = trim($_POST['title'] ?? '');

        if (!$url && !$text) {
            $this->flash('danger', 'Paste a URL or job description text.');
            $this->redirect('/submit');
        }

        $stamp = date('Ymd_His');
        $slug  = $title ? preg_replace('/[^a-z0-9]+/i', '-', strtolower(substr($title, 0, 60))) : $stamp;

        $entry = [
            'submitted_at' => date('Y-m-d H:i:s'),
            'url'          => $url,
            'title'        => $title,
            'text'         => $text,
            'source'       => $url ? 'url' : 'text',
            'processed'    => false,
        ];

        // Save as pending job for process_jobs.php to pick up
        $file = BASE_PATH . "/jobs/pending_{$slug}_{$stamp}.json";
        file_put_contents($file, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // If it's a pasted description, also save as a .txt so the processor can parse it
        if ($text) {
            $txtFile = BASE_PATH . "/jobs/submitted_{$slug}_{$stamp}.txt";
            $header  = "Title: $title\n";
            if ($url) $header .= "URL: $url\n";
            $header .= "Submitted: " . date('Y-m-d H:i:s') . "\n";
            $header .= str_repeat('-', 60) . "\n\n";
            file_put_contents($txtFile, $header . $text);
        }

        $this->flash('success', "Job submitted! " . ($url ? "URL saved." : "Text saved.") . " Run process_jobs.php to generate materials.");
        $this->redirect('/submit');
    }
}
