<?php
class DashboardController extends Controller {
    public function index(): void {
        $this->requireAuth();
        $listings = new Listing();
        $tracks = new JobTrack();
        $runs = new ScraperRun();
        $this->view('dashboard/index', [
            'counts'      => $listings->counts(),
            'tracks'      => $tracks->active(),
            'top'         => $listings->search(['status' => 'new'], 15),
            'recent_runs' => $runs->recent(10),
        ]);
    }
}
