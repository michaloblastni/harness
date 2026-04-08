<?php

/** Views: `messages/*` */
class MessagesController
{
    private AuthService $auth;
    private VictimService $victims;
    private MessageService $messages;

    public function __construct(AuthService $auth, VictimService $victims, MessageService $messages)
    {
        $this->auth = $auth;
        $this->victims = $victims;
        $this->messages = $messages;
    }

    public function list(&$model)
    {
        $this->auth->requireLogin();
        $victim = $this->victims->requireCurrentVictimWithMessages($this->auth);
        $model['victim'] = $victim->asProfileViewData();

        return [
            'view' => 'messages/list',
            'layout' => LAYOUT_LOGGED_IN,
        ];
    }

    public function detailForm(&$model)
    {
        $this->auth->requireLogin();
        $model['errors'] = [];
        $model['old'] = ['content' => ''];

        return [
            'view' => 'messages/detail',
            'layout' => LAYOUT_LOGGED_IN,
        ];
    }

    public function addMessage(&$model)
    {
        $this->auth->requireLogin();
        if (!require_csrf_post($this->auth)) {
            return null;
        }

        $content = trim((string) ($_POST['content'] ?? ''));
        if ($content === '') {
            $model['errors'] = ['content' => [tr('Please add what you heard.')]];
            $model['old'] = ['content' => ''];

            return [
                'view' => 'messages/detail',
                'layout' => LAYOUT_LOGGED_IN,
            ];
        }

        $user = $this->victims->requireVictimForLoggedInUser($this->auth);

        $this->messages->attachContentToVictim($user->id, $content);

        return ':/messages/list';
    }

    public function ping(&$model)
    {
        $this->auth->requireLogin();
        plain_response(tr('Poong!'));
    }

    /**
     * UTF-8 CSV of saved lines and linked usernames (excluding self).
     */
    public function exportCsv(&$model)
    {
        $this->auth->requireLogin();
        $victim = $this->victims->requireCurrentVictimWithMessages($this->auth);
        $data = $victim->asProfileViewData();
        $selfId = (int) $data['id'];

        $filename = 'harness-lines-' . gmdate('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        if ($out === false) {
            http_response_code(500);
            echo tr('Error');
            exit;
        }

        fputcsv($out, [tr('Saved line'), tr('Date saved'), tr('Linked usernames')]);

        foreach ($data['messages'] as $msg) {
            $linked = [];
            foreach ($msg['victims'] ?? [] as $v) {
                if ((int) ($v['id'] ?? 0) !== $selfId) {
                    $linked[] = (string) ($v['username'] ?? '');
                }
            }
            $saved = harness_format_saved_at($msg['saved_at'] ?? null);
            fputcsv($out, [(string) ($msg['content'] ?? ''), $saved, implode(', ', $linked)]);
        }

        fclose($out);
        exit;
    }
}
