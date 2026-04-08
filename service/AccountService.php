<?php

class AccountService
{
    private $victims;
    private $lastRegistrationErrors = [];

    public function __construct($victims)
    {
        $this->victims = $victims;
    }

    public function lastRegistrationErrors()
    {
        return $this->lastRegistrationErrors;
    }

    public function validateRegistration($input)
    {
        $this->lastRegistrationErrors = [];

        $u = trim($input['username'] ?? '');
        $e = trim($input['email'] ?? '');
        $p = $input['password'] ?? '';
        $pc = $input['password_confirm'] ?? '';

        if ($u === '') {
            $this->addError('username', tr('Username cannot be empty!'));
        } elseif (strlen($u) < 3 || strlen($u) > 32) {
            $this->addError('username', tr('Username must have between 3 and 32 characters.'));
        } elseif ($this->victims->findByUsername($u) !== null) {
            $this->addError('username', tr('User with this username already exists.'));
        }

        if ($p === '') {
            $this->addError('password', tr('Password cannot be empty!'));
        } elseif (strlen($p) < 6 || strlen($p) > 32) {
            $this->addError('password', tr('Password must have between 6 and 32 characters.'));
        }

        if ($pc !== $p) {
            $this->addError('password_confirm', tr('Passwords do not match'));
        }

        if ($e === '') {
            $this->addError('email', tr('Email cannot be empty!'));
        } elseif (filter_var($e, FILTER_VALIDATE_EMAIL) === false) {
            $this->addError('email', tr('Please enter a valid email address.'));
        }

        return $this->lastRegistrationErrors === [];
    }

    private function addError($field, $message)
    {
        $this->lastRegistrationErrors[$field][] = $message;
    }

    public function createUser($username, $email, $plainPassword)
    {
        $hash = password_hash($plainPassword, PASSWORD_BCRYPT);

        return $this->victims->insert(trim($username), $hash, trim($email));
    }

    public function verifyCredentials($username, $plainPassword)
    {
        $user = $this->victims->findByUsername(trim($username));
        if ($user === null || $user->passwordHash === null) {
            return null;
        }
        if (!password_verify($plainPassword, $user->passwordHash)) {
            return null;
        }

        return $user->id;
    }

    /**
     * Find or create a victim from a verified Google account.
     * Does not link Google to rows that already have a password (prevents account takeover).
     *
     * @return array{id:int,username:string}|array{error:string}
     */
    public function completeGoogleLogin(string $googleSub, string $email, bool $emailVerified)
    {
        $email = strtolower(trim($email));
        if (!$emailVerified || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['error' => 'email'];
        }

        $bySub = $this->victims->findByGoogleSub($googleSub);
        if ($bySub !== null) {
            return ['id' => (int) $bySub->id, 'username' => $bySub->username];
        }

        $byEmail = $this->victims->findByEmail($email);
        if ($byEmail !== null) {
            if ($byEmail->passwordHash !== null) {
                return ['error' => 'email_taken_password'];
            }
            if ($byEmail->googleSub !== null && $byEmail->googleSub !== $googleSub) {
                return ['error' => 'google_conflict'];
            }
            $this->victims->setGoogleSub($byEmail->id, $googleSub);

            return ['id' => (int) $byEmail->id, 'username' => $byEmail->username];
        }

        $username = $this->uniqueUsernameFromEmail($email);
        $id = $this->victims->insert($username, null, $email, $googleSub);

        return ['id' => $id, 'username' => $username];
    }

    private function uniqueUsernameFromEmail(string $email): string
    {
        $local = strstr($email, '@', true);
        $local = $local === false ? 'user' : strtolower($local);
        $local = preg_replace('/[^a-z0-9._-]+/', '', $local);
        if ($local === '' || strlen($local) < 3) {
            $local = 'user' . substr(bin2hex(random_bytes(4)), 0, 6);
        }
        $base = substr($local, 0, 32);
        $n = 0;
        do {
            if ($n === 0) {
                $candidate = $base;
            } else {
                $suffix = (string) $n;
                $candidate = substr($base, 0, max(3, 32 - strlen($suffix))) . $suffix;
            }
            if ($this->victims->findByUsername($candidate) === null) {
                return $candidate;
            }
            $n++;
        } while ($n < 10000);

        return $base . substr(bin2hex(random_bytes(4)), 0, 8);
    }

    private $lastPasswordResetErrors = [];

    public function lastPasswordResetErrors(): array
    {
        return $this->lastPasswordResetErrors;
    }

    public function requestPasswordReset(string $identifier): void
    {
        $v = $this->victims->findByUsernameOrEmail(trim($identifier));
        if ($v === null || $v->passwordHash === null) {
            return;
        }
        $email = trim((string) $v->email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $expires = gmdate('Y-m-d H:i:s', time() + 86400);
        $this->victims->setPasswordResetToken((int) $v->id, $hash, $expires);

        $url = harness_public_app_url() . '/account/reset-password?token=' . rawurlencode($raw);
        $subject = 'Harness: reset your password';
        $body = "Hello {$v->username},\r\n\r\n"
            . "We received a request to reset your Harness password. Open this link (valid 24 hours):\r\n\r\n"
            . "{$url}\r\n\r\n"
            . "If you did not ask for this, you can ignore this email.\r\n\r\n"
            . "— Harness\r\n";
        if (!harness_send_plain_mail($email, $subject, $body)) {
            error_log('Harness: password reset mail failed for victim id ' . $v->id);
        }
    }

    public function resetPasswordTokenValid(string $rawToken): bool
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '') {
            return false;
        }

        return $this->victims->findIdByPasswordResetTokenHash(hash('sha256', $rawToken)) !== null;
    }

    public function completePasswordReset(string $rawToken, string $password, string $passwordConfirm): bool
    {
        $this->lastPasswordResetErrors = [];
        $rawToken = trim($rawToken);
        if ($rawToken === '') {
            $this->lastPasswordResetErrors['token'][] = tr('Reset link invalid or expired.');

            return false;
        }

        if ($password === '') {
            $this->lastPasswordResetErrors['password'][] = tr('Password cannot be empty!');
        } elseif (strlen($password) < 6 || strlen($password) > 32) {
            $this->lastPasswordResetErrors['password'][] = tr('Password must have between 6 and 32 characters.');
        }
        if ($passwordConfirm !== $password) {
            $this->lastPasswordResetErrors['password_confirm'][] = tr('Passwords do not match');
        }
        if ($this->lastPasswordResetErrors !== []) {
            return false;
        }

        $hash = hash('sha256', $rawToken);
        $id = $this->victims->findIdByPasswordResetTokenHash($hash);
        if ($id === null) {
            $this->lastPasswordResetErrors['token'][] = tr('Reset link invalid or expired.');

            return false;
        }

        $newHash = password_hash($password, PASSWORD_BCRYPT);
        $this->victims->updatePasswordHash($id, $newHash);
        $this->victims->clearPasswordResetToken($id);

        return true;
    }
}
