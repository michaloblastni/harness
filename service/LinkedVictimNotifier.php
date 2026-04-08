<?php

/** Sends optional email when a new shared-line link appears between victims. */
class LinkedVictimNotifier
{
    private $victims;

    public function __construct(VictimRepository $victims)
    {
        $this->victims = $victims;
    }

    public function onNewLink(int $joinerVictimId, int $messageId): void
    {
        $all = $this->victims->findVictimsForMessage($messageId);
        if (count($all) < 2) {
            return;
        }

        $joiner = null;
        foreach ($all as $v) {
            if ((int) $v->id === $joinerVictimId) {
                $joiner = $v;
                break;
            }
        }
        if ($joiner === null) {
            return;
        }

        $others = [];
        foreach ($all as $v) {
            if ((int) $v->id !== $joinerVictimId) {
                $others[] = $v;
            }
        }

        $base = harness_public_app_url();
        $linkedUrl = $base . '/linked-victims';

        foreach ($others as $other) {
            if (!$other->notifyEmailOnNewLink || $other->email === null || $other->email === '') {
                continue;
            }
            if ($this->victims->hasLinkNotificationSent((int) $other->id, (int) $joiner->id)) {
                continue;
            }
            $subject = 'Harness: new linked victim';
            $body = "Hello,\r\n\r\n"
                . "Another Harness user (username: {$joiner->username}) saved the same line as you. You are now linked.\r\n\r\n"
                . "Open linked victims:\r\n{$linkedUrl}\r\n\r\n"
                . "— Harness\r\n";
            if (harness_send_plain_mail((string) $other->email, $subject, $body)) {
                $this->victims->recordLinkNotificationSent((int) $other->id, (int) $joiner->id);
            } else {
                error_log('Harness: link notification mail failed for victim id ' . $other->id);
            }
        }

        $newPeersForJoiner = [];
        foreach ($others as $other) {
            if (!$this->victims->hasLinkNotificationSent((int) $joiner->id, (int) $other->id)) {
                $newPeersForJoiner[] = $other;
            }
        }

        if ($joiner->notifyEmailOnNewLink && $joiner->email !== null && $joiner->email !== '' && $newPeersForJoiner !== []) {
            $names = implode(', ', array_map(static function ($v) {
                return $v->username;
            }, $newPeersForJoiner));
            $subject = 'Harness: you have new linked victims';
            $body = "Hello,\r\n\r\n"
                . "You saved a line that other users already had. You are now linked with: {$names}\r\n\r\n"
                . "{$linkedUrl}\r\n\r\n"
                . "— Harness\r\n";
            if (harness_send_plain_mail((string) $joiner->email, $subject, $body)) {
                foreach ($newPeersForJoiner as $other) {
                    $this->victims->recordLinkNotificationSent((int) $joiner->id, (int) $other->id);
                }
            } else {
                error_log('Harness: link notification mail failed for joiner id ' . $joiner->id);
            }
        }
    }
}
