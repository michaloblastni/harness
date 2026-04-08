<?php

/**
 * Cross-cutting helpers for escaping, translation, and CSRF validation.
 */

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Supported UI languages. Add a row and (optionally) a locale folder + .po when translations exist.
 * `gettext_locale` enables PHP gettext + GNU message catalogs under locale/{locale}/LC_MESSAGES/.
 *
 * @return array<string, array{native: string, tag: string, html_lang: string, gettext_locale: ?string}>
 */
function i18n_languages(): array
{
    static $langs = null;
    if ($langs !== null) {
        return $langs;
    }

    return $langs = [
        'en' => ['native' => 'English', 'tag' => 'EN', 'html_lang' => 'en-US', 'gettext_locale' => null],
        'cz' => ['native' => 'Čeština', 'tag' => 'CZ', 'html_lang' => 'cs', 'gettext_locale' => 'cs_CZ'],
        'sk' => ['native' => 'Slovenčina', 'tag' => 'SK', 'html_lang' => 'sk', 'gettext_locale' => 'sk_SK'],
        'de' => ['native' => 'Deutsch', 'tag' => 'DE', 'html_lang' => 'de', 'gettext_locale' => 'de_DE'],
        'fr' => ['native' => 'Français', 'tag' => 'FR', 'html_lang' => 'fr', 'gettext_locale' => 'fr_FR'],
        'es' => ['native' => 'Español', 'tag' => 'ES', 'html_lang' => 'es', 'gettext_locale' => 'es_ES'],
        'it' => ['native' => 'Italiano', 'tag' => 'IT', 'html_lang' => 'it', 'gettext_locale' => 'it_IT'],
        'pl' => ['native' => 'Polski', 'tag' => 'PL', 'html_lang' => 'pl', 'gettext_locale' => 'pl_PL'],
        'pt' => ['native' => 'Português', 'tag' => 'PT', 'html_lang' => 'pt', 'gettext_locale' => 'pt_PT'],
        'nl' => ['native' => 'Nederlands', 'tag' => 'NL', 'html_lang' => 'nl', 'gettext_locale' => 'nl_NL'],
    ];
}

function i18n_lang(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return 'en';
    }

    $code = isset($_SESSION['lang']) ? (string) $_SESSION['lang'] : 'en';

    return isset(i18n_languages()[$code]) ? $code : 'en';
}

function i18n_html_lang(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return 'en-US';
    }

    $langs = i18n_languages();
    $code = i18n_lang();

    return $langs[$code]['html_lang'] ?? 'en-US';
}

/**
 * Translate for templates and PHP. English = msgid as-is.
 * Other languages: locale/{gettext_locale}/harness.php (merged PHP catalog), else LC_MESSAGES/harness.po, else Czech legacy paths.
 */
function tr($msgid)
{
    $code = i18n_lang();
    if ($code === 'en') {
        return $msgid;
    }

    static $catalogs = [];
    if (!isset($catalogs[$code])) {
        $catalogs[$code] = i18n_load_message_catalog($code);
    }

    $catalog = $catalogs[$code];
    $out = $catalog[$msgid] ?? null;
    if ($out !== null && $out !== '') {
        return $out;
    }

    return $msgid;
}

/**
 * @return array<string, string>
 */
function i18n_load_message_catalog(string $langCode): array
{
    $langs = i18n_languages();
    $gettextLocale = $langs[$langCode]['gettext_locale'] ?? null;
    if ($gettextLocale === null) {
        return [];
    }

    $php = __DIR__ . '/locale/' . $gettextLocale . '/harness.php';
    if (is_file($php)) {
        $data = require $php;

        return is_array($data) ? $data : [];
    }

    $po = __DIR__ . '/locale/' . $gettextLocale . '/LC_MESSAGES/harness.po';
    if (is_file($po)) {
        return i18n_parse_po_file($po);
    }

    if ($langCode === 'cz') {
        return i18n_load_legacy_czech_catalog();
    }

    return [];
}

/**
 * @return array<string, string>
 */
function i18n_load_legacy_czech_catalog(): array
{
    $cached = __DIR__ . '/locale/cs_CZ/harness_catalog.php';
    if (is_file($cached)) {
        $data = require $cached;

        return is_array($data) ? $data : [];
    }

    $po = __DIR__ . '/locale/cs_CZ/LC_MESSAGES/harness.po';

    return is_file($po) ? i18n_parse_po_file($po) : [];
}

function harness_support_email(): string
{
    static $email = null;
    if ($email !== null) {
        return $email;
    }
    $cfg = @require __DIR__ . '/config.php';
    if (!is_array($cfg)) {
        $email = 'michaloblastni@gmail.com';

        return $email;
    }
    $email = isset($cfg['support_email']) && is_string($cfg['support_email']) && $cfg['support_email'] !== ''
        ? $cfg['support_email']
        : 'michaloblastni@gmail.com';

    return $email;
}

function harness_version(): string
{
    static $v = null;
    if ($v !== null) {
        return $v;
    }
    $cfg = @require __DIR__ . '/config.php';
    if (!is_array($cfg)) {
        $v = '0.1.0';

        return $v;
    }
    $v = isset($cfg['version']) && is_string($cfg['version']) && $cfg['version'] !== ''
        ? $cfg['version']
        : '0.1.0';

    return $v;
}

/**
 * Minimal GNU gettext PO parser (msgid/msgstr pairs only; skips header msgid "").
 *
 * @return array<string, string>
 */
function i18n_parse_po_file(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return [];
    }

    $result = [];
    $msgid = null;
    $msgstr = null;
    $mode = null;

    $flush = static function () use (&$result, &$msgid, &$msgstr): void {
        if ($msgid === null || $msgid === '') {
            return;
        }
        if ($msgstr === null || $msgstr === '') {
            return;
        }
        $result[$msgid] = $msgstr;
    };

    foreach ($lines as $line) {
        $line = rtrim($line, "\r\n");
        if (preg_match('/^msgid\s+"(.*)"\s*$/', $line, $m)) {
            $flush();
            $msgid = stripcslashes($m[1]);
            $msgstr = null;
            $mode = 'id';

            continue;
        }
        if (preg_match('/^msgstr\s+"(.*)"\s*$/', $line, $m)) {
            $msgstr = stripcslashes($m[1]);
            $mode = 'str';

            continue;
        }
        if (preg_match('/^"(.*)"\s*$/', $line, $m)) {
            $chunk = stripcslashes($m[1]);
            if ($mode === 'id') {
                $msgid .= $chunk;
            } elseif ($mode === 'str') {
                $msgstr .= $chunk;
            }
        }
    }
    $flush();

    return $result;
}

/**
 * Session + optional gettext when a compiled .mo exists (UI strings primarily use harness.php catalogs).
 */
function i18n_bootstrap()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $langs = i18n_languages();
    if (isset($_GET['lang']) && isset($langs[$_GET['lang']])) {
        $_SESSION['lang'] = $_GET['lang'];
    }

    $effective = $_SESSION['lang'] ?? 'en';
    if (!isset($langs[$effective])) {
        $_SESSION['lang'] = 'en';
    }

    $lang = $_SESSION['lang'] ?? 'en';
    $gettextLocale = $langs[$lang]['gettext_locale'] ?? null;
    if ($gettextLocale === null || !function_exists('bindtextdomain')) {
        return;
    }

    $mo = __DIR__ . '/locale/' . $gettextLocale . '/LC_MESSAGES/harness.mo';
    if (!is_file($mo)) {
        return;
    }

    $locale = $gettextLocale;
    $textDomain = 'harness';
    $localedir = __DIR__ . '/locale';
    $candidates = i18n_setlocale_candidates($locale);
    $applied = setlocale(LC_ALL, ...$candidates);
    if ($applied !== false) {
        putenv('LC_ALL=' . $applied);
        putenv('LANG=' . $applied);
        putenv('LANGUAGE=' . $applied);
    } else {
        putenv('LC_ALL=' . $locale . '.UTF-8');
        putenv('LANG=' . $locale . '.UTF-8');
        putenv('LANGUAGE=' . $locale . '.UTF-8');
    }

    bindtextdomain($textDomain, $localedir);
    bind_textdomain_codeset($textDomain, 'UTF-8');
    textdomain($textDomain);
}

/**
 * @return string[]
 */
function i18n_setlocale_candidates($locale)
{
    $candidates = [
        $locale . '.UTF-8',
        $locale,
        str_replace('_', '-', $locale) . '.UTF-8',
    ];
    if (PHP_OS_FAMILY === 'Windows') {
        $candidates = array_merge($candidates, [
            'Czech_Czech Republic.1250',
            'Czech.1250',
        ]);
    }

    return $candidates;
}

/** Current path + ?lang=… so switching language keeps the same page (avoids relative ?lang= pitfalls). */
function lang_switch_url($code)
{
    $path = request_path();

    return $path . '?lang=' . rawurlencode($code);
}

function request_path()
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $path = trim((string) $path);
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }
    if ($path === '') {
        $path = '/';
    }

    return $path;
}

/** Format victim_message.saved_at (UTC `Y-m-d H:i:s` from DB) for display. */
function harness_format_saved_at(?string $utcDatetime): string
{
    if ($utcDatetime === null || trim($utcDatetime) === '') {
        return '';
    }
    $utcDatetime = trim($utcDatetime);
    $t = strtotime($utcDatetime . ' UTC');
    if ($t === false) {
        return $utcDatetime;
    }

    return gmdate('Y-m-d H:i', $t) . ' UTC';
}

/** True when $href is the current path (footer nav). $href must start with "/". */
function harness_footer_nav_is_active(string $href): bool
{
    $href = trim($href);
    if ($href === '' || ($href[0] ?? '') !== '/') {
        return false;
    }
    $p = request_path();
    $norm = rtrim($href, '/');
    if ($norm === '' || $norm === '/') {
        return $p === '/' || $p === '';
    }

    return $p === $norm;
}

/**
 * Logged-in top nav: same as footer for exact paths; for non-root hrefs also true under that prefix
 * (e.g. /linked-victims/5 matches /linked-victims).
 */
function harness_main_nav_is_active(string $href): bool
{
    if (harness_footer_nav_is_active($href)) {
        return true;
    }
    $href = trim($href);
    if ($href === '' || ($href[0] ?? '') !== '/') {
        return false;
    }
    $norm = rtrim($href, '/');
    if ($norm === '' || $norm === '/') {
        return false;
    }
    $p = request_path();

    return str_starts_with($p, $norm . '/');
}

function request_method()
{
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

function redirect_response($to, $code = 302)
{
    header('Location: ' . $to, true, $code);
    exit;
}

/** Public site URL (no trailing slash). Uses HARNESS_PUBLIC_BASE_URL or the current request. */
function harness_public_app_url(): string
{
    $base = getenv('HARNESS_PUBLIC_BASE_URL');
    if (is_string($base) && $base !== '') {
        return rtrim($base, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

/**
 * Plain UTF-8 email via PHP mail(). Set HARNESS_MAIL_FROM (e.g. "Harness <mail@yourdomain>").
 */
function harness_send_plain_mail(string $to, string $subject, string $body): bool
{
    $to = trim($to);
    if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }
    $from = getenv('HARNESS_MAIL_FROM');
    $from = is_string($from) && $from !== '' ? $from : 'Harness <noreply@localhost>';
    $headers = 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'From: ' . $from . "\r\n";
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return @mail($to, $encSubject, $body, $headers);
}

/**
 * Standard response when a POST is missing or has an invalid CSRF token.
 */
function csrf_forbidden()
{
    http_response_code(403);
    echo tr('Invalid CSRF token');

    return null;
}

function plain_response($body, $code = 200)
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $body;
    exit;
}

/**
 * POST CSRF check. Returns false after sending 403 if the token is missing or invalid.
 */
function require_csrf_post(AuthService $auth)
{
    if ($auth->csrfMatches($_POST['_csrf'] ?? null)) {
        return true;
    }
    csrf_forbidden();

    return false;
}

/**
 * Whether to show class, file, line, and stack trace on the error page (still redacted).
 * Disabled when HARNESS_PRODUCTION=1 or true.
 */
function harness_show_exception_technical_details(): bool
{
    $v = getenv('HARNESS_PRODUCTION');
    if ($v === false || $v === '') {
        return true;
    }

    return !($v === '1' || strcasecmp((string) $v, 'true') === 0 || strcasecmp((string) $v, 'yes') === 0);
}

/**
 * Remove database password, DSN, and related config/env substrings from user-visible diagnostics.
 */
function harness_redact_secrets(string $text): string
{
    if ($text === '') {
        return $text;
    }

    $chunks = [];
    $cfg = @require __DIR__ . '/config.php';
    if (is_array($cfg) && isset($cfg['db']) && is_array($cfg['db'])) {
        foreach (['pass', 'password'] as $k) {
            $v = $cfg['db'][$k] ?? null;
            if (is_string($v) && strlen($v) > 1) {
                $chunks[] = $v;
            }
        }
    }

    foreach (['HARNESS_DB_PASS', 'HARNESS_DB_DSN'] as $envKey) {
        $ev = getenv($envKey);
        if (is_string($ev) && strlen($ev) > 1) {
            $chunks[] = $ev;
        }
    }

    $chunks = array_values(array_unique($chunks));
    usort($chunks, static function ($a, $b) {
        return strlen($b) <=> strlen($a);
    });

    foreach ($chunks as $c) {
        if (strpos($text, $c) !== false) {
            $text = str_replace($c, '***REDACTED***', $text);
        }
    }

    $text = preg_replace('/\b(password|pwd)\s*=\s*\S+/i', '$1=***REDACTED***', $text) ?? $text;

    return $text;
}

/**
 * @return array{message:string,error_class:string,file:string,line:int,trace:string,show_technical:bool}
 */
function harness_pack_exception_for_error_view(Throwable $e): array
{
    $show = harness_show_exception_technical_details();
    $message = harness_redact_secrets($e->getMessage());

    if (!$show) {
        return [
            'message' => $message,
            'error_class' => '',
            'file' => '',
            'line' => 0,
            'trace' => '',
            'show_technical' => false,
        ];
    }

    $trace = harness_redact_secrets($e->getTraceAsString());
    $p = $e->getPrevious();
    $depth = 0;
    while ($p !== null && $depth < 8) {
        $trace .= "\n\n--- Caused by " . get_class($p) . " ---\n";
        $trace .= harness_redact_secrets($p->getMessage()) . "\n";
        $trace .= harness_redact_secrets($p->getTraceAsString());
        $p = $p->getPrevious();
        $depth++;
    }

    return [
        'message' => $message,
        'error_class' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $trace,
        'show_technical' => true,
    ];
}
