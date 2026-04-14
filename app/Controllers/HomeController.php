<?php
class HomeController extends Controller {
    public function index(): void {
        // Decoy public page — looks like a foot-traffic analytics landing
        $this->view('home/index');
    }
    public function robots(): void {
        header('Content-Type: text/plain');
        echo "User-agent: *\nDisallow: /\n";
    }
}
