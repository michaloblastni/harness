<?php

/** View: `main` */
class MainController
{
    private AuthService $auth;
    private VictimService $victims;

    public function __construct(AuthService $auth, VictimService $victims)
    {
        $this->auth = $auth;
        $this->victims = $victims;
    }

    public function index(&$model)
    {
        $this->auth->requireLogin();
        $model['dashboard'] = $this->victims->dashboardSummaryForLoggedInUser($this->auth);

        return [
            'view' => 'main',
            'layout' => LAYOUT_LOGGED_IN,
        ];
    }
}
