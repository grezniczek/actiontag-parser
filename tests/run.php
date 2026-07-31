<?php

require_once __DIR__ . '/../classes/ActionTagParser.php';

use ActionTagParser\ActionTagParser;

$fixtures = require __DIR__ . '/fixtures.php';
$failures = [];

foreach ($fixtures as $name => $fixture) {
    $result = ActionTagParser::parse($fixture['annotation'], ['mode' => $fixture['mode']] + ($fixture['options'] ?? []));
    $tags = $result['tags'];

    $actualNames = array_column($tags, 'name');
    if (isset($fixture['tag_names']) && $actualNames !== $fixture['tag_names']) {
        $failures[] = "$name: tag names " . json_encode($actualNames);
    }
    if (isset($fixture['tag_ranges'])) {
        $actualRanges = array_map(static fn (array $tag) => [$tag['start'], $tag['end']], $tags);
        if ($actualRanges !== $fixture['tag_ranges']) $failures[] = "$name: tag ranges " . json_encode($actualRanges);
    }
    if (isset($fixture['parameter_kinds'])) {
        $actualKinds = array_map(static fn (array $tag) => $tag['parameter']['kind'] ?? null, $tags);
        if ($actualKinds !== $fixture['parameter_kinds']) $failures[] = "$name: parameter kinds " . json_encode($actualKinds);
    }
    foreach (['enabled', 'explicitly_disabled'] as $key) {
        if (isset($fixture[$key])) {
            $actual = array_column($tags, $key);
            if ($actual !== $fixture[$key]) $failures[] = "$name: $key " . json_encode($actual);
        }
    }
    if (isset($fixture['conditions'])) {
        $actual = array_map(static fn (array $condition) => $condition['raw'], array_values($result['conditions']));
        if ($actual !== $fixture['conditions']) $failures[] = "$name: conditions " . json_encode($actual);
    }
    if (isset($fixture['conditional'])) {
        $actual = array_map(static fn (array $tag) => array_map(static fn (array $ref) => [$ref['id'], $ref['negated']], $tag['conditional']), $tags);
        if ($actual !== $fixture['conditional']) $failures[] = "$name: conditional " . json_encode($actual);
    }
    if (isset($fixture['node_types'])) {
        $actual = array_column($result['nodes'], 'type');
        if ($actual !== $fixture['node_types']) $failures[] = "$name: node types " . json_encode($actual);
    }
    if (array_key_exists('diagnostic_codes', $fixture)) {
        $actual = array_column($result['diagnostics'] ?? [], 'code');
        sort($actual);
        $expected = $fixture['diagnostic_codes'];
        sort($expected);
        if ($actual !== $expected) $failures[] = "$name: diagnostic codes " . json_encode($actual);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $failures) . "\n");
    exit(1);
}

echo 'PASS (' . count($fixtures) . " fixtures)\n";
