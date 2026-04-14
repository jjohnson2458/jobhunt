<?php
class ApplicationController extends Controller {
    /**
     * List all application folders
     */
    public function index(): void {
        $this->requireAuth();
        $dir = BASE_PATH . '/applications';
        $folders = [];
        foreach (glob("$dir/*/job_summary.txt") as $f) {
            $slug = basename(dirname($f));
            $summary = file_get_contents($f);
            // Parse first few lines for display
            $title = $company = $salary = $location = $deadline = '';
            foreach (explode("\n", $summary) as $line) {
                if (str_starts_with($line, 'Title:'))    $title    = trim(substr($line, 6));
                if (str_starts_with($line, 'Company:'))  $company  = trim(substr($line, 8));
                if (str_starts_with($line, 'Salary:'))   $salary   = trim(substr($line, 7));
                if (str_starts_with($line, 'Location:')) $location = trim(substr($line, 9));
                if (str_starts_with($line, 'Deadline:')) $deadline = trim(substr($line, 9));
            }
            $hasCover = file_exists(dirname($f) . '/cover_letter.txt')
                        && filesize(dirname($f) . '/cover_letter.txt') > 20;
            $hasResume = !!glob(dirname($f) . '/resume_tailored.*');
            $folders[] = compact('slug', 'title', 'company', 'salary', 'location', 'deadline', 'hasCover', 'hasResume');
        }
        $this->view('applications/index', ['folders' => $folders]);
    }

    /**
     * Show a single application folder — cover letter, resume download, apply info
     */
    public function show(string $slug): void {
        $this->requireAuth();
        $dir = BASE_PATH . '/applications/' . basename($slug);
        if (!is_dir($dir)) { http_response_code(404); echo "Not found"; return; }

        $summary = file_exists("$dir/job_summary.txt") ? file_get_contents("$dir/job_summary.txt") : '';
        $coverLetter = file_exists("$dir/cover_letter.txt") ? file_get_contents("$dir/cover_letter.txt") : '';
        $resumeFile = glob("$dir/resume_tailored.*")[0] ?? null;

        // Parse apply info from summary
        $applyEmail = ''; $applyRef = ''; $applyDeadline = ''; $sourceUrl = '';
        if (preg_match('/Email:\s*(.+)/i', $summary, $m)) $applyEmail = trim($m[1]);
        if (preg_match('/Ref:\s*(.+)/i', $summary, $m))   $applyRef = trim($m[1]);
        if (preg_match('/Deadline:\s*(.+)/i', $summary, $m)) $applyDeadline = trim($m[1]);

        // Get source URL from listing
        $db = Database::getInstance();
        $company = $title = '';
        foreach (explode("\n", $summary) as $line) {
            if (str_starts_with($line, 'Title:'))   $title   = trim(substr($line, 6));
            if (str_starts_with($line, 'Company:')) $company = trim(substr($line, 8));
        }
        $stmt = $db->prepare("SELECT source_url FROM listings WHERE company LIKE ? AND title LIKE ? LIMIT 1");
        $stmt->execute(["%$company%", "%$title%"]);
        $row = $stmt->fetch();
        if ($row) $sourceUrl = $row['source_url'];

        $this->view('applications/show', compact(
            'slug', 'summary', 'coverLetter', 'resumeFile',
            'applyEmail', 'applyRef', 'applyDeadline', 'sourceUrl',
            'company', 'title'
        ));
    }

    /**
     * Download a file from the application folder
     */
    public function download(string $slug, string $file): void {
        $this->requireAuth();
        $path = BASE_PATH . '/applications/' . basename($slug) . '/' . basename($file);
        if (!file_exists($path)) { http_response_code(404); echo "Not found"; return; }
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $mime = match($ext) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pdf'  => 'application/pdf',
            'txt'  => 'text/plain',
            default => 'application/octet-stream',
        };
        header("Content-Type: $mime");
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
