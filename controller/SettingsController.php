<?php

/** Views: `settings/*` */
class SettingsController
{
    private AuthService $auth;
    private SettingsService $settings;

    public function __construct(AuthService $auth, SettingsService $settings)
    {
        $this->auth = $auth;
        $this->settings = $settings;
    }

    public function show(&$model)
    {
        $this->auth->requireLogin();
        $v = $this->settings->victimForLoggedInUser($this->auth);
        if ($v === null) {
            return ':/account/login';
        }

        $this->populateSettingsViewModel($model, $v);
        $model['saved'] = isset($_GET['saved']);
        $model['password_saved'] = isset($_GET['password_saved']);

        return [
            'view' => 'settings/index',
            'layout' => LAYOUT_LOGGED_IN,
        ];
    }

    public function showChangePassword(&$model)
    {
        $this->auth->requireLogin();
        $v = $this->settings->victimForLoggedInUser($this->auth);
        if ($v === null) {
            return ':/account/login';
        }
        if ($v->passwordHash === null) {
            return ':/settings';
        }

        $model['pageTitle'] = tr('Change password') . ' — ' . tr('Harness');

        return [
            'view' => 'settings/change_password',
            'layout' => LAYOUT_LOGGED_IN,
        ];
    }

    private function populateSettingsViewModel(array &$model, Victim $v): void
    {
        $model['pageTitle'] = tr('Account settings') . ' — ' . tr('Harness');
        $model['notifyEmailOnNewLink'] = $v->notifyEmailOnNewLink;
        $model['registeredEmail'] = (string) $v->email;
        $model['canChangePassword'] = $v->passwordHash !== null;
    }

    public function save(&$model)
    {
        $this->auth->requireLogin();
        if (!require_csrf_post($this->auth)) {
            return null;
        }

        $on = isset($_POST['notify_email_on_new_link']) && (string) $_POST['notify_email_on_new_link'] === '1';
        if (!$this->settings->saveNotifyEmailOnNewLink($this->auth, $on)) {
            return ':/settings';
        }

        return ':/settings?saved=1';
    }

    public function savePassword(&$model)
    {
        $this->auth->requireLogin();
        if (!require_csrf_post($this->auth)) {
            return null;
        }

        $v = $this->settings->victimForLoggedInUser($this->auth);
        if ($v === null) {
            return ':/account/login';
        }
        if ($v->passwordHash === null) {
            return ':/settings';
        }

        $result = $this->settings->changePassword(
            $this->auth,
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['new_password_confirm'] ?? '')
        );

        if (!$result['ok']) {
            $model['pageTitle'] = tr('Change password') . ' — ' . tr('Harness');
            $model['passwordErrors'] = $result['errors'];

            return [
                'view' => 'settings/change_password',
                'layout' => LAYOUT_LOGGED_IN,
            ];
        }

        return ':/settings?password_saved=1';
    }
}
