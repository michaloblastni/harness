<?php

class MessageService
{
    private $messages;
    private $victims;
    private $linkNotifier;

    public function __construct($messages, $victims, LinkedVictimNotifier $linkNotifier)
    {
        $this->messages = $messages;
        $this->victims = $victims;
        $this->linkNotifier = $linkNotifier;
    }

    public function attachContentToVictim($victimId, $content)
    {
        $content = trim($content);
        $existing = $this->messages->findByContent($content);
        $messageId = $existing !== null
            ? $existing->id
            : $this->messages->insert($content);

        $inserted = $this->victims->linkVictimToMessage($victimId, $messageId);
        if ($inserted) {
            $this->linkNotifier->onNewLink((int) $victimId, (int) $messageId);
        }
    }
}
