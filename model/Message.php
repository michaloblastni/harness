<?php

/** One shared text row (many victims can attach the same row). */
class Message
{
    public $id;
    public $content;
    public $victims;
    /** @var string|null UTC datetime when this victim linked the line (victim_message.saved_at); null if unknown */
    public $savedAt;

    public function __construct($id, $content, $victims = [], $savedAt = null)
    {
        $this->id = $id;
        $this->content = $content;
        $this->victims = $victims;
        $this->savedAt = $savedAt !== null && $savedAt !== '' ? (string) $savedAt : null;
    }

    public static function fromRow($row)
    {
        $saved = $row['saved_at'] ?? null;

        return new self(
            (int) $row['id'],
            $row['content'],
            [],
            $saved !== null && $saved !== '' ? (string) $saved : null
        );
    }
}
