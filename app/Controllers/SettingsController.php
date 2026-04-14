<?php
class SettingsController extends Controller {
    public function index(): void {
        $this->requireAuth();
        $this->view('settings/index', ['settings' => (new Setting())->all()]);
    }
    public function update(): void {
        $this->requireAuth();
        $s = new Setting();
        foreach (($_POST['settings'] ?? []) as $k => $v) {
            $s->set((string)$k, (string)$v);
        }
        $this->flash('success', 'Settings saved.');
        $this->redirect('/settings');
    }
}
