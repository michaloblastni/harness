<?php

class VictimRepository
{
    public function count()
    {
        return (int) DatabaseService::pdo()->query('SELECT COUNT(*) FROM victim')->fetchColumn();
    }

    public function findByUsername($username)
    {
        $stmt = DatabaseService::pdo()->prepare(
            'SELECT id, username, email, password, google_sub, notify_email_on_new_link FROM victim WHERE username = ?'
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Victim::fromRow($row) : null;
    }

    public function findById($id)
    {
        $stmt = DatabaseService::pdo()->prepare(
            'SELECT id, username, email, password, google_sub, notify_email_on_new_link FROM victim WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Victim::fromRow($row) : null;
    }

    public function findByEmail($email)
    {
        $stmt = DatabaseService::pdo()->prepare(
            'SELECT id, username, email, password, google_sub, notify_email_on_new_link FROM victim WHERE LOWER(email) = LOWER(?) ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([trim($email)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Victim::fromRow($row) : null;
    }

    public function findByGoogleSub($googleSub)
    {
        $stmt = DatabaseService::pdo()->prepare(
            'SELECT id, username, email, password, google_sub, notify_email_on_new_link FROM victim WHERE google_sub = ?'
        );
        $stmt->execute([$googleSub]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Victim::fromRow($row) : null;
    }

    public function setGoogleSub($victimId, $googleSub)
    {
        $stmt = DatabaseService::pdo()->prepare('UPDATE victim SET google_sub = ? WHERE id = ?');
        $stmt->execute([$googleSub, (int) $victimId]);
    }

    public function findByUsernameOrEmail(string $identifier): ?Victim
    {
        $q = trim($identifier);
        if ($q === '') {
            return null;
        }
        if (strpos($q, '@') !== false) {
            $v = $this->findByEmail($q);
            if ($v !== null) {
                return $v;
            }
        }

        return $this->findByUsername($q);
    }

    public function setPasswordResetToken($victimId, $tokenHash, $expiresUtcDatetime)
    {
        $stmt = DatabaseService::pdo()->prepare(
            'UPDATE victim SET password_reset_token_hash = ?, password_reset_expires = ? WHERE id = ?'
        );
        $stmt->execute([$tokenHash, $expiresUtcDatetime, (int) $victimId]);
    }

    public function findIdByPasswordResetTokenHash(string $hash): ?int
    {
        $stmt = DatabaseService::pdo()->prepare(
            'SELECT id, password_reset_expires FROM victim WHERE password_reset_token_hash = ? LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $exp = $row['password_reset_expires'] ?? null;
        if ($exp === null || $exp === '') {
            return null;
        }
        $t = strtotime($exp . ' UTC');
        if ($t === false || $t < time()) {
            return null;
        }

        return (int) $row['id'];
    }

    public function clearPasswordResetToken($victimId)
    {
        $stmt = DatabaseService::pdo()->prepare(
            'UPDATE victim SET password_reset_token_hash = NULL, password_reset_expires = NULL WHERE id = ?'
        );
        $stmt->execute([(int) $victimId]);
    }

    public function updatePasswordHash($victimId, $passwordHash)
    {
        $stmt = DatabaseService::pdo()->prepare('UPDATE victim SET password = ? WHERE id = ?');
        $stmt->execute([$passwordHash, (int) $victimId]);
    }

    public function countMessagesForVictim($victimId)
    {
        $stmt = DatabaseService::pdo()->prepare(
            'SELECT COUNT(*) FROM victim_message WHERE victim_id = ?'
        );
        $stmt->execute([(int) $victimId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Phrases most recently associated (by message id).
     *
     * @return list<array{id:int,content:string}>
     */
    public function recentMessageRowsForVictim($victimId, $limit = 5)
    {
        $limit = max(1, min(20, (int) $limit));
        $sql = 'SELECT m.id, m.content, vm.saved_at FROM message m
                INNER JOIN victim_message vm ON vm.message_id = m.id
                WHERE vm.victim_id = ?
                ORDER BY vm.saved_at DESC, m.id DESC
                LIMIT ?';
        $stmt = DatabaseService::pdo()->prepare($sql);
        $stmt->bindValue(1, (int) $victimId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'content' => $row['content'],
                'saved_at' => isset($row['saved_at']) && $row['saved_at'] !== '' ? (string) $row['saved_at'] : null,
            ];
        }

        return $out;
    }

    public function findMessagesForVictim($victimId)
    {
        $sql = 'SELECT m.id, m.content, vm.saved_at FROM message m
                INNER JOIN victim_message vm ON vm.message_id = m.id
                WHERE vm.victim_id = ?
                ORDER BY vm.saved_at DESC, m.id DESC';
        $stmt = DatabaseService::pdo()->prepare($sql);
        $stmt->execute([$victimId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = Message::fromRow($row);
        }

        return $out;
    }

    public function findVictimsForMessage($messageId)
    {
        $sql = 'SELECT v.id, v.username, v.email, v.password, v.google_sub, v.notify_email_on_new_link FROM victim v
                INNER JOIN victim_message vm ON vm.victim_id = v.id
                WHERE vm.message_id = ?
                ORDER BY v.username';
        $stmt = DatabaseService::pdo()->prepare($sql);
        $stmt->execute([$messageId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = Victim::fromRow($row);
        }

        return $out;
    }

    /**
     * @return bool true if a new victim_message row was inserted
     */
    public function linkVictimToMessage($victimId, $messageId)
    {
        $at = gmdate('Y-m-d H:i:s');
        $stmt = DatabaseService::pdo()->prepare(
            'INSERT IGNORE INTO victim_message (victim_id, message_id, saved_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([(int) $victimId, (int) $messageId, $at]);

        return $stmt->rowCount() > 0;
    }

    public function updateNotifyEmailOnNewLink($victimId, $enabled)
    {
        $stmt = DatabaseService::pdo()->prepare(
            'UPDATE victim SET notify_email_on_new_link = ? WHERE id = ?'
        );
        $stmt->execute([(int) (bool) $enabled, (int) $victimId]);
    }

    public function insert($username, $passwordHash, $email, $googleSub = null)
    {
        $pdo = DatabaseService::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO victim (username, password, email, google_sub, notify_email_on_new_link) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([$username, $passwordHash, $email, $googleSub]);

        return (int) $pdo->lastInsertId();
    }

    public function hasLinkNotificationSent(int $notifierVictimId, int $peerVictimId): bool
    {
        $stmt = DatabaseService::pdo()->prepare(
            'SELECT 1 FROM victim_link_notification WHERE notifier_victim_id = ? AND peer_victim_id = ? LIMIT 1'
        );
        $stmt->execute([$notifierVictimId, $peerVictimId]);

        return (bool) $stmt->fetchColumn();
    }

    public function recordLinkNotificationSent(int $notifierVictimId, int $peerVictimId): void
    {
        $at = gmdate('Y-m-d H:i:s');
        $stmt = DatabaseService::pdo()->prepare(
            'INSERT IGNORE INTO victim_link_notification (notifier_victim_id, peer_victim_id, notified_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$notifierVictimId, $peerVictimId, $at]);
    }

    public function linkVictimToMessagesByContent($victimId, $contents)
    {
        $pdo = DatabaseService::pdo();
        $find = $pdo->prepare('SELECT id FROM message WHERE content = ?');
        foreach ($contents as $content) {
            $find->execute([$content]);
            $id = $find->fetchColumn();
            if ($id) {
                $this->linkVictimToMessage($victimId, (int) $id);
            }
        }
    }

    /**
     * Insults (message rows) both victims have logged — the basis for “linked victims”.
     *
     * @return Message[]
     */
    public function findSharedMessagesBetweenVictims($victimIdA, $victimIdB)
    {
        $sql = 'SELECT m.id, m.content FROM message m
                INNER JOIN victim_message va ON va.message_id = m.id AND va.victim_id = ?
                INNER JOIN victim_message vb ON vb.message_id = m.id AND vb.victim_id = ?
                ORDER BY m.id';
        $stmt = DatabaseService::pdo()->prepare($sql);
        $stmt->execute([(int) $victimIdA, (int) $victimIdB]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = Message::fromRow($row);
        }

        return $out;
    }

    /**
     * Shared lines between viewer and peer, with peer’s saved_at on victim_message.
     *
     * @return Message[]
     */
    public function findSharedMessagesBetweenVictimsWithPeerSavedAt(int $viewerVictimId, int $peerVictimId)
    {
        $sql = 'SELECT m.id, m.content, vm_peer.saved_at AS saved_at FROM message m
                INNER JOIN victim_message vm_self ON vm_self.message_id = m.id AND vm_self.victim_id = ?
                INNER JOIN victim_message vm_peer ON vm_peer.message_id = m.id AND vm_peer.victim_id = ?
                ORDER BY vm_peer.saved_at DESC, m.id DESC';
        $stmt = DatabaseService::pdo()->prepare($sql);
        $stmt->execute([(int) $viewerVictimId, (int) $peerVictimId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = Message::fromRow($row);
        }

        return $out;
    }
}
