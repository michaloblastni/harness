<?php

/** Views: `account/*` */
class AccountController
{
    private AuthService $auth;
    private AccountService $accounts;

    public function __construct(AuthService $auth, AccountService $accounts)
    {
        $this->auth = $auth;
        $this->accounts = $accounts;
    }

    public function registrationForm(&$model)
    {
        $model['errors'] = [];
        $model['old'] = ['username' => '', 'email' => '', 'password' => '', 'password_confirm' => ''];

        return [
            'view' => 'account/registration',
            'layout' => LAYOUT_LOGGED_OUT,
        ];
    }

    public function register(&$model)
    {
        if (!require_csrf_post($this->auth)) {
            return null;
        }

        $input = [
            'username' => $_POST['username'] ?? '',
            'email' => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
        ];

        if (!$this->accounts->validateRegistration($input)) {
            $model['errors'] = $this->accounts->lastRegistrationErrors();
            $model['old'] = [
                'username' => $input['username'],
                'email' => $input['email'],
                'password' => '',
                'password_confirm' => '',
            ];

            return [
                'view' => 'account/registration',
                'layout' => LAYOUT_LOGGED_OUT,
            ];
        }

        $id = $this->accounts->createUser(
            trim($input['username']),
            trim($input['email']),
            $input['password']
        );
        $this->auth->login($id, trim($input['username']));

        return ':/';
    }

    public function loginForm(&$model)
    {
        list($error, $message) = $this->loginFlashFromQuery();
        $model['error'] = $error;
        $model['message'] = $message;

        return [
            'view' => 'account/login',
            'layout' => LAYOUT_LOGGED_OUT,
        ];
    }

    private function loginFlashFromQuery()
    {
        $error = null;
        if (isset($_GET['error'])) {
            $error = tr('Your username and password is invalid.');
        } elseif (isset($_GET['sessionTimeout'])) {
            $error = tr('You have been logged out due to inactivity.');
        }
        $message = null;
        if (isset($_GET['reset_done'])) {
            $message = tr('Your password has been reset. You can sign in now.');
        } elseif (isset($_GET['reset_sent'])) {
            $message = tr('If an account matches, we sent password reset instructions to its email.');
        } elseif (isset($_GET['logout'])) {
            $message = tr('You have been logged out successfully.');
        }

        return [$error, $message];
    }

    public function login(&$model)
    {
        if (!require_csrf_post($this->auth)) {
            return null;
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $id = $this->accounts->verifyCredentials($username, $password);
        if ($id === null) {
            return ':/account/login?error=1';
        }

        $this->auth->login($id, trim($username));

        return ':/';
    }

    public function logout(&$model)
    {
        $this->auth->logout();
        if (isset($_GET['sessionTimeout'])) {
            return ':/account/login?sessionTimeout=1';
        }

        return ':/account/login?logout=1';
    }

    public function forgottenPasswordForm(&$model)
    {
        if ($this->auth->isLoggedIn()) {
            return ':/';
        }

        $model['pageTitle'] = tr('Forgotten Password') . ' — ' . tr('Harness');
        $model['errors'] = [];
        $model['old'] = ['identifier' => ''];

        return [
            'view' => 'account/forgotten-password',
            'layout' => LAYOUT_LOGGED_OUT,
        ];
    }

    public function forgottenPasswordSubmit(&$model)
    {
        if ($this->auth->isLoggedIn()) {
            return ':/';
        }
        if (!require_csrf_post($this->auth)) {
            return null;
        }

        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        if ($identifier === '') {
            $model['errors'] = ['identifier' => [tr('Please enter your username or email.')]];
            $model['old'] = ['identifier' => ''];
            $model['pageTitle'] = tr('Forgotten Password') . ' — ' . tr('Harness');

            return [
                'view' => 'account/forgotten-password',
                'layout' => LAYOUT_LOGGED_OUT,
            ];
        }

        $this->accounts->requestPasswordReset($identifier);

        return ':/account/login?reset_sent=1';
    }

    public function resetPasswordForm(&$model)
    {
        if ($this->auth->isLoggedIn()) {
            return ':/';
        }

        $token = (string) ($_GET['token'] ?? '');
        $model['pageTitle'] = tr('Reset your password') . ' — ' . tr('Harness');
        $model['token'] = $token;
        $model['tokenValid'] = $this->accounts->resetPasswordTokenValid($token);
        $model['errors'] = [];
        $model['old'] = ['password' => '', 'password_confirm' => ''];

        return [
            'view' => 'account/reset-password',
            'layout' => LAYOUT_LOGGED_OUT,
        ];
    }

    public function resetPasswordSubmit(&$model)
    {
        if ($this->auth->isLoggedIn()) {
            return ':/';
        }
        if (!require_csrf_post($this->auth)) {
            return null;
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if (!$this->accounts->completePasswordReset($token, $password, $passwordConfirm)) {
            $model['pageTitle'] = tr('Reset your password') . ' — ' . tr('Harness');
            $model['token'] = $token;
            $model['tokenValid'] = $this->accounts->resetPasswordTokenValid($token);
            $model['errors'] = $this->accounts->lastPasswordResetErrors();
            $model['old'] = ['password' => '', 'password_confirm' => ''];

            return [
                'view' => 'account/reset-password',
                'layout' => LAYOUT_LOGGED_OUT,
            ];
        }

        return ':/account/login?reset_done=1';
    }
}
