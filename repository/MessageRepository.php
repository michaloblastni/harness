<?php

class MessageRepository
{
    public function count()
    {
        return (int) DatabaseService::pdo()->query('SELECT COUNT(*) FROM message')->fetchColumn();
    }

    public function findByContent($content)
    {
        $stmt = DatabaseService::pdo()->prepare('SELECT id, content FROM message WHERE content = ?');
        $stmt->execute([$content]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Message::fromRow($row) : null;
    }

    public function insert($content)
    {
        $pdo = DatabaseService::pdo();
        $stmt = $pdo->prepare('INSERT INTO message (content) VALUES (?)');
        $stmt->execute([$content]);

        return (int) $pdo->lastInsertId();
    }

    public function insertManyIgnoringDuplicates($contents)
    {
        $stmt = DatabaseService::pdo()->prepare('INSERT IGNORE INTO message (content) VALUES (?)');
        foreach ($contents as $c) {
            $stmt->execute([$c]);
        }
    }
}
