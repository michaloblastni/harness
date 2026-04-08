<?php

class PrivacyController
{
    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    public function cookieConsent(&$model)
    {
        if (!require_csrf_post($this->auth)) {
            return null;
        }
        $action = $_POST['consent_action'] ?? '';
        if ($action === 'accept_all') {
            GeoService::setConsentCookie('all');
        } elseif ($action === 'essential_only') {
            GeoService::setConsentCookie('essential');
        } else {
            return ':/';
        }

        $to = GeoService::sanitizeConsentReturnPath($_POST['return_to'] ?? '/');

        return ':' . $to;
    }
}
