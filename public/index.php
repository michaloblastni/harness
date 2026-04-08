<?php

define('LAYOUT_LOGGED_IN', 'layout_loggedin');
define('LAYOUT_LOGGED_OUT', 'layout_loggedout');

$base = dirname(__DIR__);
require $base . '/helpers.php';

spl_autoload_register(function ($class) use ($base) {
    $dirs = ['', '/model', '/repository', '/service', '/controller'];
    foreach ($dirs as $sub) {
        $file = $base . $sub . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

function view_render($view, $data, $layout, AuthService $auth)
{
    $dir = dirname(__DIR__) . '/templates/';
    $data['csrf'] = $auth->csrfToken();
    $data['currentUsername'] = $auth->username();
    extract($data, EXTR_SKIP);
    ob_start();
    require $dir . $view . '.phtml';
    $content = ob_get_clean();
    require $dir . $layout . '.phtml';
}

function view_partial($view, $data = [])
{
    $dir = dirname(__DIR__) . '/templates/';
    extract($data, EXTR_SKIP);
    require $dir . $view . '.phtml';
}

/**
 * Controllers return null if they already sent output; a string starting with ":" as redirect
 * to that path; otherwise a page or partial descriptor array (view, layout, partial).
 */
function application_render_result(AuthService $auth, $result, &$model)
{
    if ($result === null) {
        return;
    }
    if (is_string($result) && $result !== '' && $result[0] === ':') {
        redirect_response(substr($result, 1));

        return;
    }
    if (!is_array($result) || !isset($result['view'])) {
        return;
    }
    $data = array_merge($result['data'] ?? [], $model);
    if (!empty($result['partial'])) {
        view_partial($result['view'], $data);

        return;
    }
    if (!isset($result['layout'])) {
        error_log('render: page view descriptor missing layout for view: ' . $result['view']);

        return;
    }
    view_render($result['view'], $data, $result['layout'], $auth);
}

function application_error_partial($message)
{
    return [
        'view' => 'error',
        'data' => [
            'message' => $message,
            'error_class' => '',
            'file' => '',
            'line' => 0,
            'trace' => '',
            'show_technical' => false,
        ],
        'partial' => true,
    ];
}

function application_exception_partial(Throwable $e)
{
    return [
        'view' => 'error',
        'data' => harness_pack_exception_for_error_view($e),
        'partial' => true,
    ];
}

function application_dispatch_route($method, $path, &$model, array $routes, LinkedVictimsController $linkedVictims)
{
    $key = $method . ' ' . $path;
    if (isset($routes[$key])) {
        return $routes[$key]();
    }
    if ($method === 'GET' && preg_match('#^/linked-victims/([^/]+)$#', $path, $m)) {
        $model['_linkedVictimUsername'] = $m[1];

        return $linkedVictims->detail($model);
    }

    return false;
}

function application_routes(MainController $main, AccountController $account, MessagesController $messages, LinkedVictimsController $linkedVictims, PrivacyController $privacy, SitePagesController $sitePages, SettingsController $settings, &$model)
{
    return [
        'GET /' => function () use ($main, &$model) {
            return $main->index($model);
        },

        'GET /how-it-works' => function () use ($sitePages, &$model) {
            return $sitePages->howItWorks($model);
        },
        'GET /privacy-policy' => function () use ($sitePages, &$model) {
            return $sitePages->privacyPolicy($model);
        },
        'GET /support' => function () use ($sitePages, &$model) {
            return $sitePages->support($model);
        },

        'GET /account/registration' => function () use ($account, &$model) {
            return $account->registrationForm($model);
        },
        'POST /account/registration' => function () use ($account, &$model) {
            return $account->register($model);
        },

        'GET /account/login' => function () use ($account, &$model) {
            return $account->loginForm($model);
        },
        'POST /account/login' => function () use ($account, &$model) {
            return $account->login($model);
        },
        'GET /account/logout' => function () use ($account, &$model) {
            return $account->logout($model);
        },
        'GET /account/forgotten-password' => function () use ($account, &$model) {
            return $account->forgottenPasswordForm($model);
        },
        'POST /account/forgotten-password' => function () use ($account, &$model) {
            return $account->forgottenPasswordSubmit($model);
        },
        'GET /account/reset-password' => function () use ($account, &$model) {
            return $account->resetPasswordForm($model);
        },
        'POST /account/reset-password' => function () use ($account, &$model) {
            return $account->resetPasswordSubmit($model);
        },

        'GET /messages/list' => function () use ($messages, &$model) {
            return $messages->list($model);
        },
        'GET /messages/export' => function () use ($messages, &$model) {
            return $messages->exportCsv($model);
        },
        'GET /messages/detail' => function () use ($messages, &$model) {
            return $messages->detailForm($model);
        },
        'POST /messages/detail' => function () use ($messages, &$model) {
            return $messages->addMessage($model);
        },
        'GET /messages/ping' => function () use ($messages, &$model) {
            return $messages->ping($model);
        },

        'GET /linked-victims' => function () use ($linkedVictims, &$model) {
            return $linkedVictims->list($model);
        },

        'GET /settings' => function () use ($settings, &$model) {
            return $settings->show($model);
        },
        'GET /settings/password' => function () use ($settings, &$model) {
            return $settings->showChangePassword($model);
        },
        'POST /settings' => function () use ($settings, &$model) {
            return $settings->save($model);
        },
        'POST /settings/password' => function () use ($settings, &$model) {
            return $settings->savePassword($model);
        },

        'POST /privacy/cookie-consent' => function () use ($privacy, &$model) {
            return $privacy->cookieConsent($model);
        },
    ];
}

function application_run()
{
    i18n_bootstrap();

    $auth = new AuthService();
    $victimRepo = new VictimRepository();
    $messageRepo = new MessageRepository();

    try {
        (new SeedService($messageRepo, $victimRepo))->runIfEmpty();
    } catch (Throwable $e) {
        error_log('SeedService: ' . harness_redact_secrets($e->getMessage()));
    }

    $path = request_path();
    $method = request_method();

    $accountService = new AccountService($victimRepo);
    $victimService = new VictimService($victimRepo);
    $linkNotifier = new LinkedVictimNotifier($victimRepo);
    $messageService = new MessageService($messageRepo, $victimRepo, $linkNotifier);
    $settingsService = new SettingsService($victimRepo);

    $main = new MainController($auth, $victimService);
    $account = new AccountController($auth, $accountService);
    $messages = new MessagesController($auth, $victimService, $messageService);
    $linkedVictims = new LinkedVictimsController($auth, $victimService);
    $privacy = new PrivacyController($auth);
    $sitePages = new SitePagesController($auth);
    $settings = new SettingsController($auth, $settingsService);

    $model = [];
    $routes = application_routes($main, $account, $messages, $linkedVictims, $privacy, $sitePages, $settings, $model);

    try {
        $result = application_dispatch_route($method, $path, $model, $routes, $linkedVictims);
        if ($result !== false) {
            application_render_result($auth, $result, $model);

            return;
        }

        http_response_code(404);
        application_render_result($auth, application_error_partial(sprintf(tr('Page not found: %s'), $path)), $model);
    } catch (Throwable $e) {
        http_response_code(500);
        error_log('[Harness] ' . harness_redact_secrets($e->getMessage()) . "\n" . harness_redact_secrets($e->getTraceAsString()));
        application_render_result($auth, application_exception_partial($e), $model);
    }
}

application_run();
