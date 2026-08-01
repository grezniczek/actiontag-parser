<?php

require_once __DIR__ . '/../classes/ActionTagParser_Old.php';
require_once __DIR__ . '/../classes/ActionTagParser.php';

use ActionTagParser\ActionTagParser;
use ActionTagParser\ActionTagParser_Old;
use ActionTagParser\SEGTYPE;

$xmlPaths = glob(__DIR__ . '/../ProjectXML/*.xml');

$legacyLeaves = static function (array $parts) use (&$legacyLeaves): array {
    $tags = [];
    foreach ($parts as $part) {
        if (($part['type'] ?? null) !== SEGTYPE::TAG) continue;
        if (($part['text'] ?? null) === '@IF') {
            $tags = [...$tags, ...$legacyLeaves($part['if_then'] ?? []), ...$legacyLeaves($part['if_else'] ?? [])];
            continue;
        }
        $tags[] = strtoupper($part['text']);
    }
    return $tags;
};

$failures = [];
$count = 0;
$legacyWarnings = 0;
set_error_handler(static function (int $severity, string $message, string $file) use (&$legacyWarnings): bool {
    if ($severity === E_WARNING && basename($file) === 'ActionTagParser_Old.php' && str_contains($message, 'Undefined array key "param"')) {
        $legacyWarnings++;
        return true;
    }
    return false;
});
foreach ($xmlPaths as $xmlPath) {
    $xml = simplexml_load_file($xmlPath);
    if ($xml === false) {
        $failures[] = 'could not load ' . basename($xmlPath);
        continue;
    }
    $xml->registerXPathNamespace('redcap', 'https://projectredcap.org');
    foreach ($xml->xpath('//@redcap:FieldAnnotation') as $attribute) {
        $annotation = (string) $attribute;
        $legacy = $legacyLeaves(ActionTagParser_Old::parse($annotation));
        $pure = array_column(ActionTagParser::parse($annotation)['tags'], 'name');
        if ($legacy !== $pure) {
            $failures[] = basename($xmlPath) . ': leaf tags differ: legacy=' . json_encode($legacy) . ' pure=' . json_encode($pure);
        }
        $count++;
    }
}
restore_error_handler();

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $failures) . "\n");
    exit(1);
}

echo "PASS ($count bundled annotation comparisons)\n";
echo "Intentional difference: legacy exposes @IF as a leaf/container part; the pure fast parser emits only flattened branch tags with condition references.\n";
if ($legacyWarnings > 0) echo "Observed legacy-only warning: $legacyWarnings undefined param-key warnings were suppressed during comparison.\n";
