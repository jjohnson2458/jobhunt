<?php
class ListingController extends Controller {
    public function index(): void {
        $this->requireAuth();
        $model = new Listing();
        $tracks = new JobTrack();
        $filters = [
            'track_id'  => $_GET['track_id']  ?? null,
            'status'    => $_GET['status']    ?? null,
            'source'    => $_GET['source']    ?? null,
            'min_score' => $_GET['min_score'] ?? null,
        ];
        $this->view('listings/index', [
            'listings' => $model->search($filters, 200),
            'tracks'   => $tracks->findAll(),
            'filters'  => $filters,
        ]);
    }

    public function show(int $id): void {
        $this->requireAuth();
        $model = new Listing();
        $listing = $model->find($id);
        if (!$listing) { http_response_code(404); echo "Not found"; return; }
        $appModel = new Application();
        $this->view('listings/show', [
            'listing'     => $listing,
            'application' => $appModel->forListing($id),
        ]);
    }

    public function updateStatus(int $id): void {
        $this->requireAuth();
        $status = $_POST['status'] ?? 'reviewed';
        $stmt = (new Listing())->getDb()->prepare("UPDATE listings SET status=? WHERE id=?");
        // fallback: use Database directly since Model doesn't expose db
        $db = Database::getInstance();
        $db->prepare("UPDATE listings SET status=? WHERE id=?")->execute([$status, $id]);
        $this->flash('success', "Status updated to $status.");
        $this->redirect("/listings/$id");
    }

    public function generate(int $id): void {
        $this->requireAuth();
        $model = new Listing();
        $listing = $model->find($id);
        if (!$listing) { $this->redirect('/listings'); }

        $tracks = new JobTrack();
        $track = $listing['track_id'] ? $tracks->find((int)$listing['track_id']) : null;

        try {
            $gen = new CoverLetterGenerator();
            $result = $gen->generate($listing, $track);
            $appModel = new Application();
            $existing = $appModel->forListing($id);
            $payload = [
                'listing_id'        => $id,
                'status'            => 'drafted',
                'resume_path'       => $result['resume_path']      ?? null,
                'cover_letter_path' => $result['cover_letter_path']?? null,
                'cover_letter_text' => $result['cover_letter_text']?? null,
            ];
            if ($existing) {
                $db = Database::getInstance();
                $db->prepare("UPDATE applications SET resume_path=?, cover_letter_path=?, cover_letter_text=? WHERE id=?")
                   ->execute([$payload['resume_path'], $payload['cover_letter_path'], $payload['cover_letter_text'], $existing['id']]);
            } else {
                $appModel->create($payload);
            }
            $this->flash('success', 'Cover letter + tailored resume generated.');
        } catch (Throwable $e) {
            $this->flash('danger', 'Generation failed: ' . $e->getMessage());
        }
        $this->redirect("/listings/$id");
    }

    public function markApplied(int $id): void {
        $this->requireAuth();
        $listing = (new Listing())->find($id);
        if (!$listing) { $this->redirect('/listings'); }
        $appModel = new Application();
        $existing = $appModel->forListing($id);
        $db = Database::getInstance();
        if ($existing) {
            $db->prepare("UPDATE applications SET status='applied', applied_at=NOW() WHERE id=?")->execute([$existing['id']]);
        } else {
            $appModel->create(['listing_id' => $id, 'status' => 'applied']);
        }
        $appModel->recordSignature(
            Listing::makeAppliedSignature($listing['company'], $listing['title']),
            $listing['company'], $listing['title']
        );
        $db->prepare("UPDATE listings SET status='reviewed' WHERE id=?")->execute([$id]);
        $this->flash('success', 'Marked as applied.');
        $this->redirect("/listings/$id");
    }
}
