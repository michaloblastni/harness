<?php

/** Registered user. Matches the original Java domain entity name. */
class Victim
{
    public $id;
    public $username;
    public $email;
    public $passwordHash;
    /** @var string|null OpenID Connect subject from Google */
    public $googleSub;
    /** @var bool */
    public $notifyEmailOnNewLink = false;
    public $messages;

    public function __construct($id, $username, $email = null, $passwordHash = null, $messages = [], $googleSub = null, $notifyEmailOnNewLink = false)
    {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->messages = $messages;
        $this->googleSub = $googleSub;
        $this->notifyEmailOnNewLink = (bool) $notifyEmailOnNewLink;
    }

    public static function fromRow($row)
    {
        return new self(
            (int) $row['id'],
            $row['username'],
            $row['email'] ?? null,
            $row['password'] ?? null,
            [],
            isset($row['google_sub']) ? $row['google_sub'] : null,
            isset($row['notify_email_on_new_link']) ? (bool) (int) $row['notify_email_on_new_link'] : true
        );
    }

    public function withoutPassword()
    {
        return new self($this->id, $this->username, $this->email, null, $this->messages, $this->googleSub, $this->notifyEmailOnNewLink);
    }

    public function asProfileViewData()
    {
        $rows = [];
        foreach ($this->messages as $message) {
            $victims = [];
            foreach ($message->victims as $victim) {
                $victims[] = ['id' => $victim->id, 'username' => $victim->username];
            }
            $rows[] = [
                'content' => $message->content,
                'saved_at' => $message->savedAt,
                'victims' => $victims,
            ];
        }

        return [
            'id' => $this->id,
            'messages' => $rows,
        ];
    }

    public function asLinkedDetailViewData()
    {
        $lines = [];
        foreach ($this->messages as $message) {
            $lines[] = ['content' => $message->content];
        }

        return [
            'username' => $this->username,
            'email' => $this->email,
            'messages' => $lines,
        ];
    }
}
