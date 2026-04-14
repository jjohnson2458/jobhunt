<?php
class TrackController extends Controller {
    public function index(): void {
        $this->requireAuth();
        $this->view('tracks/index', ['tracks' => (new JobTrack())->findAll()]);
    }

    public function create(): void {
        $this->requireAuth();
        $this->view('tracks/edit', ['track' => null]);
    }

    public function store(): void {
        $this->requireAuth();
        $data = $this->collect();
        (new JobTrack())->create($data);
        $this->flash('success', 'Track created.');
        $this->redirect('/tracks');
    }

    public function edit(int $id): void {
        $this->requireAuth();
        $this->view('tracks/edit', ['track' => (new JobTrack())->find($id)]);
    }

    public function update(int $id): void {
        $this->requireAuth();
        (new JobTrack())->update($id, $this->collect());
        $this->flash('success', 'Track updated.');
        $this->redirect('/tracks');
    }

    public function delete(int $id): void {
        $this->requireAuth();
        (new JobTrack())->delete($id);
        $this->flash('success', 'Track deleted.');
        $this->redirect('/tracks');
    }

    private function collect(): array {
        return [
            'name'              => trim($_POST['name'] ?? ''),
            'slug'              => strtolower(preg_replace('/[^a-z0-9]+/i','-', $_POST['name'] ?? '')),
            'is_active'         => isset($_POST['is_active']) ? 1 : 0,
            'role_keywords'     => trim($_POST['role_keywords'] ?? ''),
            'exclude_keywords'  => trim($_POST['exclude_keywords'] ?? ''),
            'salary_floor'      => (int)($_POST['salary_floor'] ?? 0),
            'locations'         => trim($_POST['locations'] ?? ''),
            'remote_ok'         => isset($_POST['remote_ok']) ? 1 : 0,
            'resume_template'   => trim($_POST['resume_template'] ?? ''),
            'cover_letter_tone' => trim($_POST['cover_letter_tone'] ?? 'professional'),
            'notes'             => trim($_POST['notes'] ?? ''),
        ];
    }
}
