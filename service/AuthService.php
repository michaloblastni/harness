<?php

class AuthService
{
    public function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login($userId, $username)
    {
        $this->startSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
    }

    public function logout()
    {
        $this->startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public function isLoggedIn()
    {
        $this->startSession();

        return isset($_SESSION['user_id'], $_SESSION['username']);
    }

    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            redirect_response('/account/login');
        }
    }

    public function userId()
    {
        $this->startSession();

        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public function username()
    {
        $this->startSession();

        return $_SESSION['username'] ?? null;
    }

    public function csrfToken()
    {
        $this->startSession();
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public function csrfMatches($token)
    {
        $this->startSession();

        return is_string($token)
            && isset($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }
}
