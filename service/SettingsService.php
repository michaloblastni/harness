<?php

class SettingsService
{
    private $victims;

    public function __construct(VictimRepository $victims)
    {
        $this->victims = $victims;
    }

    public function victimForLoggedInUser(AuthService $auth): ?Victim
    {
        return $this->victims->findByUsername($auth->username() ?? '');
    }

    public function saveNotifyEmailOnNewLink(AuthService $auth, bool $enabled): bool
    {
        $v = $this->victimForLoggedInUser($auth);
        if ($v === null) {
            return false;
        }
        $this->victims->updateNotifyEmailOnNewLink($v->id, $enabled);

        return true;
    }

    /**
     * @return array{ok:true}|array{ok:false, errors:array<string, list<string>>}
     */
    public function changePassword(AuthService $auth, string $current, string $new, string $confirm): array
    {
        $v = $this->victimForLoggedInUser($auth);
        if ($v === null) {
            return ['ok' => false, 'errors' => ['general' => [tr('User not found')]]];
        }
        if ($v->passwordHash === null) {
            return ['ok' => false, 'errors' => ['general' => [tr('Password change is not available for accounts that sign in only with Google.')]]];
        }

        $errors = [];
        if ($current === '' || !password_verify($current, $v->passwordHash)) {
            $errors['current_password'][] = tr('Current password is incorrect.');
        }
        if ($new === '') {
            $errors['new_password'][] = tr('Password cannot be empty!');
        } elseif (strlen($new) < 6 || strlen($new) > 32) {
            $errors['new_password'][] = tr('Password must have between 6 and 32 characters.');
        }
        if ($confirm !== $new) {
            $errors['new_password_confirm'][] = tr('Passwords do not match');
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->victims->updatePasswordHash($v->id, password_hash($new, PASSWORD_BCRYPT));

        return ['ok' => true];
    }
}
