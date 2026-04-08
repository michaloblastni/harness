<?php

/** Views: `linked-victims/*` */
class LinkedVictimsController
{
    private AuthService $auth;
    private VictimService $victims;

    public function __construct(AuthService $auth, VictimService $victims)
    {
        $this->auth = $auth;
        $this->victims = $victims;
    }

    public function list(&$model)
    {
        $this->auth->requireLogin();
        $model['victims'] = $this->victims->linkedVictimListRowsForLoggedInUser($this->auth);

        return [
            'view' => 'linked-victims/list',
            'layout' => LAYOUT_LOGGED_IN,
        ];
    }

    public function detail(&$model)
    {
        if (!is_array($model)) {
            throw new RuntimeException(
                'Application bug: view model must be an array, got ' . (is_object($model) ? get_class($model) : gettype($model))
            );
        }
        $username = $model['_linkedVictimUsername'] ?? '';
        unset($model['_linkedVictimUsername']);

        $this->auth->requireLogin();
        $detail = $this->victims->linkedVictimDetailForViewer($this->auth, $username);
        if ($detail === null) {
            http_response_code(404);
            $model['message'] = tr('That person is not among your linked victims, or the account does not exist.');

            return [
                'view' => 'error',
                'partial' => true,
            ];
        }

        $model['linked'] = $detail;

        return [
            'view' => 'linked-victims/detail',
            'layout' => LAYOUT_LOGGED_IN,
        ];
    }
}
