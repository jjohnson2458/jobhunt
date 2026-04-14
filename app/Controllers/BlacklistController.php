<?php
class BlacklistController extends Controller {
    public function index(): void {
        $this->requireAuth();
        $this->view('blacklist/index', ['items' => (new Blacklist())->all(), 'tracks' => (new JobTrack())->findAll()]);
    }

    public function add(): void {
        $this->requireAuth();
        (new Blacklist())->create([
            'track_id' => $_POST['track_id'] ? (int)$_POST['track_id'] : null,
            'type'     => $_POST['type'] ?? 'keyword',
            'pattern'  => trim($_POST['pattern'] ?? ''),
            'reason'   => trim($_POST['reason'] ?? ''),
        ]);
        $this->redirect('/blacklist');
    }

    public function delete(int $id): void {
        $this->requireAuth();
        (new Blacklist())->delete($id);
        $this->redirect('/blacklist');
    }
}
