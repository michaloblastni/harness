<?php

/**
 * Reads locale/cs_CZ/LC_MESSAGES/harness.po and writes locale/cs_CZ/harness_catalog.php
 * so Czech works without a compiled harness.mo (common on Windows/WAMP).
 *
 * Run from project root: php tools/generate-harness-catalog.php
 */

$base = dirname(__DIR__);
$po = $base . '/locale/cs_CZ/LC_MESSAGES/harness.po';
$out = $base . '/locale/cs_CZ/harness_catalog.php';

if (!is_file($po)) {
    fwrite(STDERR, "Missing PO file: $po\n");
    exit(1);
}

$catalog = parse_po_file($po);
$php = "<?php\n\nreturn " . var_export($catalog, true) . ";\n";
if (file_put_contents($out, $php) === false) {
    fwrite(STDERR, "Could not write $out\n");
    exit(1);
}

echo "Wrote " . count($catalog) . " strings to $out\n";

/**
 * @return array<string, string>
 */
function parse_po_file(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES);
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
