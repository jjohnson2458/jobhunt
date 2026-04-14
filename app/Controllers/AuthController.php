<?php
class AuthController extends Controller {
    public function showLogin(): void {
        if (Auth::check()) { $this->redirect('/dashboard'); }
        $this->view('auth/login', ['flash' => $this->getFlash()]);
    }

    public function login(): void {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $userModel = new User();
        $user = $userModel->findBy('email', $email);
        if (!$user || !password_verify($pass, $user['password_hash'])) {
            $this->flash('danger', 'Invalid credentials.');
            $this->redirect('/login');
        }
        Auth::login($user);
        $this->redirect('/dashboard');
    }

    public function logout(): void {
        Auth::logout();
        $this->redirect('/');
    }
}
