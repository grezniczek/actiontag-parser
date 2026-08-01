<?php

require_once __DIR__ . '/../classes/ActionTagParser.php';
require_once __DIR__ . '/../classes/ActionTagParserAdapter.php';
require_once __DIR__ . '/../classes/ActionTagConditionResolver.php';
require_once __DIR__ . '/../classes/ActionTagIndex.php';
require_once __DIR__ . '/../classes/ActionTagDiagnosticLocations.php';

use ActionTagParser\ActionTagParser;
use DE\RUB\ActionTagParserExternalModule\ActionTagParserAdapter;
use ActionTagParser\ActionTagConditionResolver;
use ActionTagParser\ActionTagIndex;
use ActionTagParser\ActionTagDiagnosticLocations;

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
    if (isset($fixture['tag_contract']) && ($tags[0] ?? null) !== $fixture['tag_contract']) {
        $failures[] = "$name: full tag contract " . json_encode($tags[0] ?? null);
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
    if (($fixture['node_source_covers'] ?? false) && implode('', array_column($result['nodes'], 'raw')) !== $fixture['annotation']) {
        $failures[] = "$name: diagnostic nodes do not cover the complete source";
    }
    if (array_key_exists('diagnostic_codes', $fixture)) {
        $actual = array_column($result['diagnostics'] ?? [], 'code');
        sort($actual);
        $expected = $fixture['diagnostic_codes'];
        sort($expected);
        if ($actual !== $expected) $failures[] = "$name: diagnostic codes " . json_encode($actual);
    }
}

foreach ($fixtures as $name => $fixture) {
    $options = $fixture['options'] ?? [];
    $fast = ActionTagParser::parse($fixture['annotation'], ['mode' => 'fast'] + $options);
    $diagnostic = ActionTagParser::parse($fixture['annotation'], ['mode' => 'diagnostic'] + $options);
    $normalize = static function (array $result): array {
        return array_map(static function (array $tag) use ($result): array {
            $conditions = array_map(static fn (array $ref): array => [
                $result['conditions'][$ref['id']]['raw'],
                $ref['negated'],
            ], $tag['conditional']);
            return [$tag['name'], $tag['start'], $tag['end'], $tag['raw'], $tag['parameter'], $conditions];
        }, array_values(array_filter($result['tags'], static fn (array $tag): bool => $tag['enabled'])));
    };
    if ($normalize($fast) !== $normalize($diagnostic)) {
        $failures[] = "$name: fast and diagnostic enabled-tag outputs differ";
    }
}

// This would exceed Xdebug's call-stack guard with recursive @IF descent.
$deep = '@HIDDEN';
for ($i = 0; $i < 600; $i++) $deep = "@IF([field_$i], $deep, @READONLY)";
$deepResult = ActionTagParser::parse($deep, ['mode' => 'diagnostic', 'max_nesting_depth' => 128]);
if (count($deepResult['conditions']) !== 129 || array_column($deepResult['diagnostics'], 'code') !== ['nesting_limit_exceeded']) {
    $failures[] = 'iterative_deep_nesting: expected bounded parsing without call-stack exhaustion';
}

$adapterTags = ActionTagParserAdapter::parseActionTags("@DEFAULT='value' @IF([a], @READONLY, @HIDDEN)");
if ($adapterTags !== [
    ['actiontag' => '@DEFAULT', 'params' => "'value'", 'match' => "@DEFAULT='value'", 'start' => 0, 'end' => 16, 'conditional' => []],
    ['actiontag' => '@READONLY', 'params' => '', 'match' => '@READONLY', 'start' => 26, 'end' => 35, 'conditional' => [['id' => 1, 'negated' => false]]],
    ['actiontag' => '@HIDDEN', 'params' => '', 'match' => '@HIDDEN', 'start' => 37, 'end' => 44, 'conditional' => [['id' => 1, 'negated' => true]]],
]) {
    $failures[] = 'adapter: compatibility-shaped tag output differs';
}

$conditionalParse = ActionTagParser::parse('@IF([a], @HIDDEN, @IF([b], @READONLY, @REQUIRED))');
$evaluated = [];
$resolved = ActionTagConditionResolver::resolve($conditionalParse, [], static function (string $condition) use (&$evaluated): bool {
    $evaluated[] = $condition;
    return $condition === '[a]';
});
if ($evaluated !== ['[a]', '[b]'] || array_column($resolved['tags'], 'active') !== [true, false, false]) {
    $failures[] = 'condition_resolver: conditions were not evaluated once or tags resolved incorrectly';
}

$located = ActionTagDiagnosticLocations::enrich("é\r\n@notatag", ActionTagParser::parse("é\r\n@notatag", ['mode' => 'diagnostic']));
if (($located['diagnostics'][0]['start_line'] ?? null) !== 2 || ($located['diagnostics'][0]['start_column'] ?? null) !== 1 || ($located['diagnostics'][0]['start_byte_column'] ?? null) !== 1) {
    $failures[] = 'diagnostic_locations: Unicode/CRLF source location differs';
}

$index = ActionTagIndex::build([
    'field_a' => ['instrument' => 'form_a', 'annotation' => '@HIDDEN @IF([a], @READONLY, @REQUIRED)'],
    'field_b' => ['instrument' => 'form_b', 'annotation' => '@HIDDEN-FORM'],
    'field_c' => 'Plain text',
]);
if (array_keys($index['by_field']) !== ['field_a', 'field_b'] || count($index['by_tag']['@HIDDEN'] ?? []) !== 1 || array_keys($index['by_instrument']['form_a'] ?? []) !== ['field_a'] || ($index['conditions']['field_a'][1]['raw'] ?? null) !== '[a]') {
    $failures[] = 'action_tag_index: aggregate views differ';
}

if ($failures !== []) {
    fwrite(STDERR, "FAIL\n" . implode("\n", $failures) . "\n");
    exit(1);
}

echo 'PASS (' . count($fixtures) . " fixtures plus iterative deep-nesting check)\n";
