<?php

class VictimService
{
    private $victims;

    public function __construct($victims)
    {
        $this->victims = $victims;
    }

    public function findByUsername($username)
    {
        return $this->victims->findByUsername($username);
    }

    public function loadVictimWithMessages($username)
    {
        $victim = $this->victims->findByUsername($username);
        if ($victim === null) {
            return null;
        }

        $messages = $this->victims->findMessagesForVictim($victim->id);
        foreach ($messages as $message) {
            $message->victims = $this->victims->findVictimsForMessage($message->id);
        }

        $victim = $victim->withoutPassword();
        $victim->messages = $messages;

        return $victim;
    }

    public function linkedVictimsForCurrentUser($self)
    {
        $victim = $this->loadVictimWithMessages($self->username);
        if ($victim === null) {
            return [];
        }

        $byUsername = [];
        foreach ($victim->messages as $message) {
            if (count($message->victims) <= 1) {
                continue;
            }
            foreach ($message->victims as $other) {
                if ($other->id !== $self->id) {
                    $byUsername[$other->username] = $other;
                }
            }
        }

        return array_values($byUsername);
    }

    /**
     * @throws RuntimeException if the session user has no profile row
     */
    public function requireCurrentVictimWithMessages($auth)
    {
        $victim = $this->loadVictimWithMessages($auth->username() ?? '');
        if ($victim === null) {
            throw new RuntimeException('Current user not found');
        }

        return $victim;
    }

    /**
     * @throws RuntimeException if the session user has no victim row
     */
    public function requireVictimForLoggedInUser($auth)
    {
        $user = $this->findByUsername($auth->username() ?? '');
        if ($user === null) {
            throw new RuntimeException('Current user not found');
        }

        return $user;
    }

    /**
     * List rows: linked victims with email and how many phrases you share (for list UI).
     *
     * @return list<array{id:int,username:string,email:string,shared_count:int}>
     */
    public function linkedVictimListRowsForLoggedInUser($auth)
    {
        $self = $this->requireVictimForLoggedInUser($auth);
        $linked = $this->linkedVictimsForCurrentUser($self);
        $rows = [];
        foreach ($linked as $v) {
            $shared = $this->victims->findSharedMessagesBetweenVictims($self->id, $v->id);
            $rows[] = [
                'id' => $v->id,
                'username' => $v->username,
                'email' => (string) $v->email,
                'shared_count' => count($shared),
            ];
        }

        return $rows;
    }

    /**
     * Detail for a linked victim: only allowed if they share at least one phrase with the viewer.
     *
     * @return array{
     *   username:string,
     *   email:string,
     *   shared_phrases:list<array{content:string,saved_at:?string}>,
     *   their_phrases:list<array{content:string,saved_at:?string}>
     * }|null
     */
    public function linkedVictimDetailForViewer($auth, $targetUsername)
    {
        $self = $this->requireVictimForLoggedInUser($auth);
        $targetUsername = trim((string) $targetUsername);
        if ($targetUsername === '' || strcasecmp($targetUsername, $self->username) === 0) {
            return null;
        }

        $target = $this->victims->findByUsername($targetUsername);
        if ($target === null) {
            return null;
        }

        $linked = $this->linkedVictimsForCurrentUser($self);
        $allowed = false;
        foreach ($linked as $v) {
            if ($v->id === $target->id) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return null;
        }

        $shared = $this->victims->findSharedMessagesBetweenVictimsWithPeerSavedAt($self->id, $target->id);
        $theirMessages = $this->victims->findMessagesForVictim($target->id);

        $sharedRows = [];
        foreach ($shared as $m) {
            $sharedRows[] = ['content' => $m->content, 'saved_at' => $m->savedAt];
        }
        $theirRows = [];
        foreach ($theirMessages as $m) {
            $theirRows[] = ['content' => $m->content, 'saved_at' => $m->savedAt];
        }

        return [
            'username' => $target->username,
            'email' => (string) $target->email,
            'shared_phrases' => $sharedRows,
            'their_phrases' => $theirRows,
        ];
    }

    /**
     * Operational dashboard: counts and recent phrases for the signed-in victim.
     *
     * @return array{
     *   phrases_recorded:int,
     *   linked_members:int,
     *   linking_phrases:int,
     *   recent_phrases:list<array{id:int,content:string}>
     * }
     */
    public function dashboardSummaryForLoggedInUser($auth)
    {
        $self = $this->requireVictimForLoggedInUser($auth);
        $linked = $this->linkedVictimsForCurrentUser($self);
        $linkingPhrases = 0;
        foreach ($this->victims->findMessagesForVictim($self->id) as $message) {
            $others = $this->victims->findVictimsForMessage($message->id);
            if (count($others) > 1) {
                ++$linkingPhrases;
            }
        }

        return [
            'phrases_recorded' => $this->victims->countMessagesForVictim($self->id),
            'linked_members' => count($linked),
            'linking_phrases' => $linkingPhrases,
            'recent_phrases' => $this->victims->recentMessageRowsForVictim($self->id, 5),
        ];
    }
}
