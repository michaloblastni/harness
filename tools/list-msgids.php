<?php
$base = dirname(__DIR__);
require $base . '/helpers.php';
$po = $base . '/locale/cs_CZ/LC_MESSAGES/harness.po';
$c = i18n_parse_po_file($po);
ksort($c);
foreach (array_keys($c) as $id) {
    echo strlen($id) . "\t" . str_replace(["\n", "\r"], ' ', $id) . "\n";
}
