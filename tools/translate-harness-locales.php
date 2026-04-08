<?php
/**
 * Fills locale/{xx_XX}/harness.php using Google translate_gtx (undocumented; for development).
 * Skips cs_CZ (maintain Czech via harness.po + emit-php-catalogs.php).
 * Run: php tools/translate-harness-locales.php
 */
$base = dirname(__DIR__);
require $base . '/helpers.php';

function emit_php_catalog(string $path, array $msgidToStr): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $lines = ["<?php", '', 'return ['];
    foreach ($msgidToStr as $msgid => $str) {
        $lines[] = '    ' . var_export($msgid, true) . ' => ' . var_export($str, true) . ',';
    }
    $lines[] = '];';
    $lines[] = '';
    file_put_contents($path, implode("\n", $lines));
}

function gtx_translate(string $text, string $targetLang): string
{
    if ($text === '') {
        return '';
    }
    $url = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=en&tl=' . rawurlencode($targetLang) . '&dt=t&q=' . rawurlencode($text);
    $ctx = stream_context_create(['http' => ['timeout' => 25, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    if (!is_string($body) || $body === '') {
        return $text;
    }
    $j = json_decode($body, true);
    if (!is_array($j) || !isset($j[0]) || !is_array($j[0]) || !isset($j[0][0]) || !is_array($j[0][0])) {
        return $text;
    }
    $out = (string) ($j[0][0][0] ?? $text);

    if (preg_match('/Harness|HARNESS/u', $text) && !preg_match('/Harness/u', $out)) {
        return $text;
    }

    return $out;
}

$catalog = i18n_parse_po_file($base . '/locale/cs_CZ/LC_MESSAGES/harness.po');
ksort($catalog);
$msgids = array_keys($catalog);

$targets = [
    'de_DE' => 'de',
    'fr_FR' => 'fr',
    'es_ES' => 'es',
    'it_IT' => 'it',
    'pl_PL' => 'pl',
    'pt_PT' => 'pt',
    'nl_NL' => 'nl',
    'sk_SK' => 'sk',
];

foreach ($targets as $locale => $tl) {
    echo "Translating $locale...\n";
    $merged = [];
    $i = 0;
    foreach ($msgids as $msgid) {
        $merged[$msgid] = gtx_translate($msgid, $tl);
        $i++;
        if ($i % 4 === 0) {
            usleep(120000);
        }
    }
    emit_php_catalog($base . '/locale/' . $locale . '/harness.php', $merged);
    echo "Wrote $locale (" . count($merged) . " strings)\n";
}

echo "Done.\n";
