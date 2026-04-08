<?php

/**
 * EU/EEA/UK/CH privacy-zone detection (IP) + cookie consent helpers. No Composer.
 * Geo: Cloudflare CF-IPCountry header if present, else ip-api.com (HTTP) as fallback.
 */
class GeoService
{
    public static function euPrivacyCountryCodes(): array
    {
        return [
            'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR', 'HU', 'IE', 'IT',
            'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
            'IS', 'LI', 'NO', 'GB', 'CH',
        ];
    }

    public static function clientIp(): string
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);

            return trim($parts[0]);
        }

        return trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    public static function ipIsNonPublic(string $ip): bool
    {
        if ($ip === '' || $ip === '::1') {
            return true;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE);
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null decoded JSON or null
     */
    public static function httpGetJson(string $url, int $timeoutSeconds = 2): ?array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
            if (!is_string($body) || $body === '') {
                return null;
            }
            $data = json_decode($body, true);

            return is_array($data) ? $data : null;
        }
        $ctx = stream_context_create([
            'http' => [
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (!is_string($body) || $body === '') {
            return null;
        }
        $data = json_decode($body, true);

        return is_array($data) ? $data : null;
    }

    /**
     * ISO 3166-1 alpha-2 or null if unknown.
     */
    public static function geoCountryCode(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }
        if (array_key_exists('_harness_geo_country', $_SESSION)) {
            $cached = $_SESSION['_harness_geo_country'];

            return $cached === '' ? null : $cached;
        }

        if (getenv('HARNESS_FORCE_EU_GEO') === '1') {
            $_SESSION['_harness_geo_country'] = 'DE';

            return 'DE';
        }

        $ip = self::clientIp();

        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $cc = strtoupper(trim((string) $_SERVER['HTTP_CF_IPCOUNTRY']));
            if (strlen($cc) === 2 && ctype_alpha($cc)) {
                $_SESSION['_harness_geo_country'] = $cc;

                return $cc;
            }
        }

        if (self::ipIsNonPublic($ip)) {
            $_SESSION['_harness_geo_country'] = '';

            return null;
        }

        $url = 'http://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,countryCode';
        $data = self::httpGetJson($url, 2);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            $_SESSION['_harness_geo_country'] = '';

            return null;
        }
        $cc = strtoupper((string) ($data['countryCode'] ?? ''));
        if (strlen($cc) !== 2) {
            $_SESSION['_harness_geo_country'] = '';

            return null;
        }
        $_SESSION['_harness_geo_country'] = $cc;

        return $cc;
    }

    /**
     * Whether to show GDPR-style cookie UI (conservative if location unknown).
     */
    public static function visitorInEuPrivacyZone(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        if (array_key_exists('_harness_in_privacy_zone', $_SESSION)) {
            return (bool) $_SESSION['_harness_in_privacy_zone'];
        }

        $cc = self::geoCountryCode();
        if ($cc !== null && in_array($cc, self::euPrivacyCountryCodes(), true)) {
            $_SESSION['_harness_in_privacy_zone'] = true;

            return true;
        }

        $ip = self::clientIp();
        if ($cc === null && self::ipIsNonPublic($ip)) {
            // Loopback: show consent banner so local dev matches production behaviour.
            $loopback = ($ip === '::1' || $ip === '127.0.0.1' || strncmp($ip, '127.', 4) === 0);
            $_SESSION['_harness_in_privacy_zone'] = $loopback || getenv('HARNESS_PRIVACY_ZONE_PRIVATE_IP') === '1';

            return (bool) $_SESSION['_harness_in_privacy_zone'];
        }

        if ($cc === null) {
            $_SESSION['_harness_in_privacy_zone'] = true;

            return true;
        }

        $_SESSION['_harness_in_privacy_zone'] = false;

        return false;
    }

    public static function cookieConsentValue(): string
    {
        $v = $_COOKIE['harness_cookie_consent'] ?? '';

        return is_string($v) ? $v : '';
    }

    public static function cookieBannerShouldShow(): bool
    {
        $v = self::cookieConsentValue();
        if ($v === 'all' || $v === 'essential') {
            return false;
        }

        return self::visitorInEuPrivacyZone();
    }

    /**
     * @param 'all'|'essential' $choice
     */
    public static function setConsentCookie(string $choice): void
    {
        if ($choice !== 'all' && $choice !== 'essential') {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $opts = [
            'expires' => time() + 365 * 86400,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (PHP_VERSION_ID >= 70300) {
            setcookie('harness_cookie_consent', $choice, $opts);
        } else {
            setcookie('harness_cookie_consent', $choice, $opts['expires'], $opts['path'] . '; samesite=Lax', '', $secure, true);
        }
        $_COOKIE['harness_cookie_consent'] = $choice;
    }

    public static function sanitizeConsentReturnPath($path): string
    {
        if (!is_string($path) || $path === '') {
            return '/';
        }
        if ($path[0] !== '/') {
            return '/';
        }
        if (isset($path[1]) && $path[1] === '/') {
            return '/';
        }
        if (preg_match('#^[a-z]+:#i', $path)) {
            return '/';
        }

        return $path;
    }
}
