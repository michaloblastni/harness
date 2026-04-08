<?php
/**
 * Reads locale/cs_CZ/LC_MESSAGES/harness.po and writes locale/{LOCAL}/harness.php.
 * Merges with existing harness.php so new msgids are added without removing translations.
 * Run: php tools/emit-php-catalogs.php
 */
$base = dirname(__DIR__);
require $base . '/helpers.php';

$csPo = $base . '/locale/cs_CZ/LC_MESSAGES/harness.po';
$catalog = i18n_parse_po_file($csPo);
ksort($catalog);

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

$locales = ['cs_CZ', 'sk_SK', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pl_PL', 'pt_PT', 'nl_NL'];

foreach ($locales as $loc) {
    $path = $base . '/locale/' . $loc . '/harness.php';
    $existing = (is_file($path) && is_readable($path)) ? require $path : [];
    if (!is_array($existing)) {
        $existing = [];
    }

    $merged = [];
    foreach ($catalog as $msgid => $czechStr) {
        if ($loc === 'cs_CZ') {
            $merged[$msgid] = $czechStr;

            continue;
        }
        if (array_key_exists($msgid, $existing) && $existing[$msgid] !== $msgid) {
            $merged[$msgid] = $existing[$msgid];

            continue;
        }
        $merged[$msgid] = $existing[$msgid] ?? $msgid;
    }
    emit_php_catalog($path, $merged);
}

echo "Merged harness.php for: " . implode(', ', $locales) . "\n";
