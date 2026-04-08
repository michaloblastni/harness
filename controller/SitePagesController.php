<?php

class SitePagesController
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    private function layout(): string
    {
        return $this->auth->isLoggedIn() ? LAYOUT_LOGGED_IN : LAYOUT_LOGGED_OUT;
    }

    public function howItWorks(&$model)
    {
        $model['pageTitle'] = tr('How it works') . ' — ' . tr('Harness');

        return [
            'view' => 'pages/how_it_works',
            'layout' => $this->layout(),
        ];
    }

    public function privacyPolicy(&$model)
    {
        $model['pageTitle'] = tr('Privacy Policy') . ' — ' . tr('Harness');
        $model['supportEmail'] = harness_support_email();

        return [
            'view' => 'pages/privacy_policy',
            'layout' => $this->layout(),
        ];
    }

    public function support(&$model)
    {
        $model['pageTitle'] = tr('Support') . ' — ' . tr('Harness');
        $model['supportEmail'] = harness_support_email();

        return [
            'view' => 'pages/support',
            'layout' => $this->layout(),
        ];
    }
}
