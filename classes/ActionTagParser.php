<?php

namespace ActionTagParser;

/**
 * Portable structural parser for REDCap action-tag annotations.
 *
 * This class intentionally knows nothing about project metadata, action-tag
 * definitions, piping, or runtime @IF evaluation.  Those are callers' jobs.
 */
final class ActionTagParser
{
    private const DEFAULT_MAX_INPUT_BYTES = 1_048_576;
    private const DEFAULT_MAX_NESTING_DEPTH = 128;
    private const MAX_INPUT_BYTES = 16_777_216;
    // Keeps PHP call-stack use bounded until the planned explicit frame stack lands.
    private const MAX_NESTING_DEPTH = 128;
    private const ACCEPT_IF_THEN_SHORTHAND = false;

    /**
     * Parse an annotation into portable syntax nodes.
     *
     * @return array{mode:string, conditions:array<int,array>, tags:array, nodes?:array, diagnostics?:array}
     */
    public static function parse(string $annotation, array $options = []): array
    {
        $mode = $options['mode'] ?? 'fast';
        if ($mode !== 'fast' && $mode !== 'diagnostic') {
            throw new \InvalidArgumentException("The parser mode must be 'fast' or 'diagnostic'.");
        }

        $state = [
            'input' => $annotation,
            'length' => strlen($annotation),
            'mode' => $mode,
            'include_text_segments' => (bool) ($options['include_text_segments'] ?? false),
            'max_nesting_depth' => self::limit($options['max_nesting_depth'] ?? self::DEFAULT_MAX_NESTING_DEPTH, self::MAX_NESTING_DEPTH),
            'conditions' => [],
            'tags' => [],
            'diagnostics' => [],
            'next_condition_id' => 1,
            'delimiter_matches' => [],
        ];

        $result = ['mode' => $mode, 'conditions' => [], 'tags' => []];
        if ($mode === 'diagnostic') {
            $result['nodes'] = [];
            $result['diagnostics'] = [];
        }
        $maxInputBytes = self::limit($options['max_input_bytes'] ?? self::DEFAULT_MAX_INPUT_BYTES, self::MAX_INPUT_BYTES);
        if ($state['length'] > $maxInputBytes) {
            if ($mode === 'diagnostic') {
                $state['diagnostics'][] = self::diagnostic('input_limit_exceeded', 'error', 0, $state['length'], 'The annotation exceeds the configured input limit.');
                $result['nodes'] = [];
                $result['diagnostics'] = $state['diagnostics'];
            }
            return $result;
        }

        if (strpos($annotation, '@') !== false || ($mode === 'diagnostic' && $state['include_text_segments'])) {
            $state['delimiter_matches'] = self::buildDelimiterMap($annotation);
            self::scan($state);
            if ($mode === 'diagnostic') $result['nodes'] = $state['sinks'][0];
        }

        $result['conditions'] = $state['conditions'];
        $result['tags'] = $state['tags'];
        if ($mode === 'diagnostic') $result['diagnostics'] = $state['diagnostics'];
        return $result;
    }

    /**
     * Iterative annotation scanner. Range and @IF-completion frames replace
     * recursive descent, keeping parser stack use independent of input depth.
     */
    private static function scan(array &$state): void
    {
        $state['sinks'] = [0 => []];
        $state['next_sink_id'] = 1;
        $state['if_nodes'] = [];
        $state['next_if_node_id'] = 1;
        $stack = [[
            'type' => 'range', 'start' => 0, 'end' => $state['length'], 'i' => 0, 'text_start' => 0,
            'conditional' => [], 'enabled' => true, 'disabled_by' => null, 'depth' => 0, 'sink' => 0,
        ]];
        $input = $state['input'];

        while ($stack !== []) {
            $index = count($stack) - 1;
            $frame = &$stack[$index];
            if ($frame['type'] === 'if_after_then') {
                if ($state['mode'] === 'diagnostic') $state['if_nodes'][$frame['if_id']]['then'] = $state['sinks'][$frame['then_sink']];
                $continuation = $frame;
                unset($frame);
                array_pop($stack);
                $continuation['type'] = 'if_finish';
                $stack[] = $continuation;
                $stack[] = self::rangeFrame($continuation['else_arm'], $continuation['conditional_else'], $continuation['enabled'], $continuation['disabled_by'], $continuation['depth'] + 1, $continuation['else_sink']);
                continue;
            }
            if ($frame['type'] === 'if_finish') {
                if ($state['mode'] === 'diagnostic') {
                    $state['if_nodes'][$frame['if_id']]['else'] = $state['sinks'][$frame['else_sink']];
                    $state['sinks'][$frame['parent_sink']][] = $state['if_nodes'][$frame['if_id']];
                    unset($state['if_nodes'][$frame['if_id']]);
                }
                unset($state['sinks'][$frame['then_sink']], $state['sinks'][$frame['else_sink']]);
                unset($frame);
                array_pop($stack);
                continue;
            }

            if ($frame['i'] >= $frame['end']) {
                $nodes = &$state['sinks'][$frame['sink']];
                self::emitText($state, $nodes, $frame['text_start'], $frame['end']);
                unset($nodes, $frame);
                array_pop($stack);
                continue;
            }

            $i = $frame['i'];
            if ($input[$i] !== '@' || !self::isTagBoundaryBefore($input, $i)) { $frame['i']++; unset($frame); continue; }
            $start = $i;
            $explicitlyDisabled = false;
            $nameStart = $i + 1;
            if (substr($input, $i, 6) === '@.OFF.') { $explicitlyDisabled = true; $nameStart = $i + 6; }
            if ($nameStart >= $frame['end'] || !self::isAsciiLetter($input[$nameStart])) {
                $nodes = &$state['sinks'][$frame['sink']];
                $candidate = self::candidate($state, $start, min($start + 1, $frame['end']), 'possible_action_tag', 'Action-tag candidate does not start with a valid name.');
                if ($candidate !== null) { self::emitText($state, $nodes, $frame['text_start'], $start); $nodes[] = $candidate; $frame['text_start'] = $candidate['end']; }
                $frame['i']++;
                unset($nodes, $frame);
                continue;
            }
            $nameEnd = $nameStart + 1;
            while ($nameEnd < $frame['end'] && self::isNameCharacter($input[$nameEnd])) $nameEnd++;
            $rawName = substr($input, $start, $nameEnd - $start);
            if (!self::isUppercaseName($input, $nameStart, $nameEnd)) {
                $nodes = &$state['sinks'][$frame['sink']];
                $candidate = self::candidate($state, $start, $nameEnd, 'invalid_tag_name', 'Action-tag names must use uppercase ASCII letters.');
                if ($candidate !== null) { self::emitText($state, $nodes, $frame['text_start'], $start); $nodes[] = $candidate; $frame['text_start'] = $candidate['end']; }
                $frame['i'] = $nameEnd;
                unset($nodes, $frame);
                continue;
            }
            $sourceName = substr($input, $nameStart, $nameEnd - $nameStart);
            $name = '@' . str_replace('_', '-', $sourceName);
            if (str_contains($sourceName, '_')) {
                self::addDiagnostic($state, 'deprecated_underscore_action_tag_name', 'warning', $start, $nameEnd, 'Underscores in action-tag names are normalized to dashes and are deprecated.');
            }
            $parameterStart = $nameEnd;
            while ($parameterStart < $frame['end'] && self::isWhitespace($input[$parameterStart])) $parameterStart++;
            $introducer = $parameterStart < $frame['end'] ? $input[$parameterStart] : '';
            $bareTag = $nameEnd === $frame['end'] || (self::isWhitespace($input[$nameEnd]) && $introducer !== '=' && $introducer !== '(');

            if ($name === '@IF' && $introducer === '(') {
                $close = self::scanDelimited($state, $parameterStart, $frame['end'], '(', ')');
                $nodes = &$state['sinks'][$frame['sink']];
                self::emitText($state, $nodes, $frame['text_start'], $start);
                if ($close === null) {
                    self::addDiagnostic($state, 'unterminated_if', 'error', $start, $frame['end'], '@IF does not reach its closing parenthesis.');
                    self::emitStructuralCandidate($state, $nodes, $start, $frame['end']);
                    $frame['i'] = $frame['end']; $frame['text_start'] = $frame['end'];
                    unset($nodes, $frame);
                    continue;
                }
                $frame['i'] = $close + 1; $frame['text_start'] = $close + 1;
                if ($state['mode'] === 'fast' && $explicitlyDisabled) { unset($nodes, $frame); continue; }
                $arms = self::splitIfArms($state, $parameterStart + 1, $close);
                if (count($arms) > 3) { self::addDiagnostic($state, 'if_unexpected_top_level_separator', 'error', $start, $close + 1, '@IF has more than three top-level arguments.'); self::emitStructuralCandidate($state, $nodes, $start, $close + 1); unset($nodes, $frame); continue; }
                if (count($arms) < 2 || trim(substr($input, $arms[0][0], $arms[0][1] - $arms[0][0])) === '') { self::addDiagnostic($state, 'if_missing_condition', 'error', $start, $close + 1, '@IF requires a condition and a true branch.'); self::emitStructuralCandidate($state, $nodes, $start, $close + 1); unset($nodes, $frame); continue; }
                if (trim(substr($input, $arms[1][0], $arms[1][1] - $arms[1][0])) === '') { self::addDiagnostic($state, 'if_missing_then', 'error', $start, $close + 1, '@IF requires a true branch.'); self::emitStructuralCandidate($state, $nodes, $start, $close + 1); unset($nodes, $frame); continue; }
                if (count($arms) === 2 && !self::ACCEPT_IF_THEN_SHORTHAND) { self::addDiagnostic($state, 'if_missing_else', 'error', $start, $close + 1, '@IF requires a false branch.'); self::emitStructuralCandidate($state, $nodes, $start, $close + 1); unset($nodes, $frame); continue; }
                if (count($arms) === 2) $arms[] = [$close, $close];
                $conditionId = $state['next_condition_id']++;
                $state['conditions'][$conditionId] = ['raw' => substr($input, $arms[0][0], $arms[0][1] - $arms[0][0]), 'start' => $arms[0][0], 'end' => $arms[0][1]];
                $enabled = $frame['enabled'] && !$explicitlyDisabled;
                $childDisabledBy = $explicitlyDisabled ? $conditionId : $frame['disabled_by'];
                $thenConditional = [...$frame['conditional'], ['id' => $conditionId, 'negated' => false]];
                $elseConditional = [...$frame['conditional'], ['id' => $conditionId, 'negated' => true]];
                if ($state['mode'] === 'diagnostic') {
                    $ifId = $state['next_if_node_id']++;
                    $ifNode = ['type' => 'if', 'name' => '@IF', 'raw_name' => $rawName, 'start' => $start, 'end' => $close + 1, 'raw' => substr($input, $start, $close - $start + 1), 'enabled' => $enabled, 'explicitly_disabled' => $explicitlyDisabled, 'conditional' => $frame['conditional'], 'condition_id' => $conditionId, 'then' => [], 'else' => []];
                    if (!$enabled && $frame['disabled_by'] !== null) $ifNode['disabled_by'] = $frame['disabled_by'];
                    $state['if_nodes'][$ifId] = $ifNode;
                } else $ifId = 0;
                if ($frame['depth'] >= $state['max_nesting_depth']) {
                    self::addDiagnostic($state, 'nesting_limit_exceeded', 'error', $start, $close + 1, 'The configured nesting limit was exceeded.');
                    if ($state['mode'] === 'diagnostic') { $state['sinks'][$frame['sink']][] = $state['if_nodes'][$ifId]; unset($state['if_nodes'][$ifId]); }
                    unset($nodes, $frame);
                    continue;
                }
                $thenSink = $state['next_sink_id']++; $elseSink = $state['next_sink_id']++; $state['sinks'][$thenSink] = []; $state['sinks'][$elseSink] = [];
                $continuation = ['type' => 'if_after_then', 'if_id' => $ifId, 'then_sink' => $thenSink, 'else_sink' => $elseSink, 'parent_sink' => $frame['sink'], 'else_arm' => $arms[2], 'conditional_else' => $elseConditional, 'conditional_then' => $thenConditional, 'enabled' => $enabled, 'disabled_by' => $childDisabledBy, 'depth' => $frame['depth']];
                unset($nodes, $frame);
                $stack[] = $continuation;
                $stack[] = self::rangeFrame($arms[1], $thenConditional, $enabled, $childDisabledBy, $continuation['depth'] + 1, $thenSink);
                continue;
            }

            $nodes = &$state['sinks'][$frame['sink']];
            if ($introducer === '=') {
                [$parameter, $after] = self::parseAssignment($state, $parameterStart, $frame['end']);
                if ($parameter !== null) { self::emitText($state, $nodes, $frame['text_start'], $start); self::emitTag($state, $nodes, $name, $rawName, $start, $after, $parameter, $frame['conditional'], $frame['enabled'], $explicitlyDisabled, $frame['disabled_by']); $frame['text_start'] = $after; }
                else { self::emitText($state, $nodes, $frame['text_start'], $start); self::emitStructuralCandidate($state, $nodes, $start, $after); $frame['text_start'] = $after; }
                $frame['i'] = max($after, $i + 1); unset($nodes, $frame); continue;
            }
            if ($introducer === '(') {
                $close = self::scanDelimited($state, $parameterStart, $frame['end'], '(', ')');
                if ($close === null) {
                    $unbalanced = self::hasMismatchedDelimiter($input, $parameterStart, $frame['end']);
                    self::addDiagnostic(
                        $state,
                        $unbalanced ? 'unbalanced_parameter_delimiter' : 'unterminated_parenthesized_parameter',
                        'error',
                        $start,
                        $frame['end'],
                        $unbalanced ? 'Parenthesized action-tag parameter has an unexpected closing delimiter.' : 'Parenthesized action-tag parameter is not closed.'
                    );
                    $frame['i'] = self::nextTopLevelTagStart($input, $parameterStart + 1, $frame['end']) ?? $frame['end'];
                    self::emitText($state, $nodes, $frame['text_start'], $start);
                    self::emitStructuralCandidate($state, $nodes, $start, $frame['i']);
                    $frame['text_start'] = $frame['i'];
                    unset($nodes, $frame);
                    continue;
                }
                $parameter = ['kind' => 'arguments', 'start' => $parameterStart, 'end' => $close + 1, 'raw' => substr($input, $parameterStart, $close - $parameterStart + 1), 'value' => substr($input, $parameterStart + 1, $close - $parameterStart - 1)];
                self::emitText($state, $nodes, $frame['text_start'], $start); self::emitTag($state, $nodes, $name, $rawName, $start, $close + 1, $parameter, $frame['conditional'], $frame['enabled'], $explicitlyDisabled, $frame['disabled_by']); $frame['i'] = $close + 1; $frame['text_start'] = $close + 1; unset($nodes, $frame); continue;
            }
            if ($bareTag || $parameterStart === $frame['end']) {
                self::emitText($state, $nodes, $frame['text_start'], $start); self::emitTag($state, $nodes, $name, $rawName, $start, $nameEnd, null, $frame['conditional'], $frame['enabled'], $explicitlyDisabled, $frame['disabled_by']); $frame['i'] = $nameEnd; $frame['text_start'] = $nameEnd; unset($nodes, $frame); continue;
            }
            $candidate = self::candidate($state, $start, $nameEnd, 'invalid_tag_name', 'Action-tag name is not followed by a valid boundary or parameter introducer.');
            if ($candidate !== null) { self::emitText($state, $nodes, $frame['text_start'], $start); $nodes[] = $candidate; $frame['text_start'] = $candidate['end']; }
            $frame['i'] = $nameEnd;
            unset($nodes, $frame);
        }
    }

    /** @param array{0:int,1:int} $range */
    private static function rangeFrame(array $range, array $conditional, bool $enabled, ?int $disabledBy, int $depth, int $sink): array
    {
        return ['type' => 'range', 'start' => $range[0], 'end' => $range[1], 'i' => $range[0], 'text_start' => $range[0], 'conditional' => $conditional, 'enabled' => $enabled, 'disabled_by' => $disabledBy, 'depth' => $depth, 'sink' => $sink];
    }

    /**  array{0:?array,1:int} */
    private static function parseAssignment(array &$state, int $equals, int $end): array
    {
        $input = $state['input'];
        $valueStart = $equals + 1;
        while ($valueStart < $end && self::isWhitespace($input[$valueStart])) $valueStart++;
        if ($valueStart >= $end) {
            self::addDiagnostic($state, 'unterminated_quoted_parameter', 'error', $equals, $end, 'Assignment parameter has no value.');
            return [null, $end];
        }

        $first = $input[$valueStart];
        if ($first === "'" || $first === '"') {
            $close = self::scanQuoted($input, $valueStart, $end, $first);
            if ($close === null) {
                self::addDiagnostic($state, 'unterminated_quoted_parameter', 'error', $valueStart, $end, 'Quoted action-tag parameter is not closed.');
                return [null, self::nextTopLevelTagStart($input, $valueStart + 1, $end) ?? $end];
            }
            $raw = substr($input, $valueStart, $close - $valueStart + 1);
            $value = substr($input, $valueStart + 1, $close - $valueStart - 1);
            $parameter = ['kind' => 'quoted', 'start' => $valueStart, 'end' => $close + 1, 'raw' => $raw, 'value' => $value];
            if ($first === "'" && str_starts_with($value, '{')) {
                $decoded = self::decodeJson($value);
                if ($decoded['valid'] && is_array($decoded['value']) && !array_is_list($decoded['value'])) {
                    $parameter['kind'] = 'json';
                    $parameter['value'] = $decoded['value'];
                    self::addDiagnostic($state, 'deprecated_single_quoted_json_object_parameter', 'warning', $valueStart, $close + 1, 'Single-quoted JSON object parameters are deprecated.');
                }
            }
            return [$parameter, $close + 1];
        }

        if ($first === '[' || $first === '{') {
            $close = self::scanDelimited($state, $valueStart, $end, $first, $first === '[' ? ']' : '}');
            if ($close === null) {
                // Once strict scanning fails, recover a balanced JSON-like
                // value so it can remain an unquoted string after JSON decode.
                $close = self::scanPermissiveDelimited($input, $valueStart, $end, $first, $first === '[' ? ']' : '}');
            }
            if ($close !== null) {
                $raw = substr($input, $valueStart, $close - $valueStart + 1);
                $decoded = self::decodeJson($raw);
                if ($decoded['valid']) {
                    return [['kind' => 'json', 'start' => $valueStart, 'end' => $close + 1, 'raw' => $raw, 'value' => $decoded['value']], $close + 1];
                }
                self::addDiagnostic($state, 'deprecated_unquoted_parameter', 'warning', $valueStart, $close + 1, 'Unquoted action-tag parameters are deprecated.');
                return [['kind' => 'unquoted', 'start' => $valueStart, 'end' => $close + 1, 'raw' => $raw, 'value' => $raw], $close + 1];
            }
        }

        $valueEnd = $valueStart;
        while ($valueEnd < $end && !self::isWhitespace($input[$valueEnd])) $valueEnd++;
        $raw = substr($input, $valueStart, $valueEnd - $valueStart);
        self::addDiagnostic($state, 'deprecated_unquoted_parameter', 'warning', $valueStart, $valueEnd, 'Unquoted action-tag parameters are deprecated.');
        return [['kind' => 'unquoted', 'start' => $valueStart, 'end' => $valueEnd, 'raw' => $raw, 'value' => $raw], $valueEnd];
    }

    /**  list<array> $nodes  list<array{id:int,negated:bool}> $conditional */
    private static function emitTag(array &$state, array &$nodes, string $name, string $rawName, int $start, int $end, ?array $parameter, array $conditional, bool $inheritedEnabled, bool $explicitlyDisabled, ?int $disabledBy): void
    {
        $enabled = $inheritedEnabled && !$explicitlyDisabled;
        $tag = [
            'type' => 'tag', 'name' => $name, 'raw_name' => $rawName,
            'start' => $start, 'end' => $end, 'raw' => substr($state['input'], $start, $end - $start),
            'enabled' => $enabled, 'explicitly_disabled' => $explicitlyDisabled,
            'parameter' => $parameter, 'conditional' => $conditional,
        ];
        if (!$enabled && $disabledBy !== null) $tag['disabled_by'] = $disabledBy;
        if ($state['mode'] === 'diagnostic') {
            $nodes[] = $tag;
            $state['tags'][] = $tag;
        } elseif ($enabled) {
            $state['tags'][] = $tag;
        }
    }

    /** @param list<array> $nodes */
    private static function emitText(array $state, array &$nodes, int $start, int $end): void
    {
        if ($state['mode'] === 'diagnostic' && $state['include_text_segments'] && $end > $start) {
            $nodes[] = ['type' => 'text', 'start' => $start, 'end' => $end, 'raw' => substr($state['input'], $start, $end - $start)];
        }
    }

    /** @param list<array> $nodes */
    private static function emitStructuralCandidate(array $state, array &$nodes, int $start, int $end): void
    {
        if ($state['mode'] === 'diagnostic') {
            $nodes[] = ['type' => 'candidate', 'start' => $start, 'end' => $end, 'raw' => substr($state['input'], $start, $end - $start)];
        }
    }

    /** @return list<array{int,int}> */
    private static function splitIfArms(array $state, int $start, int $end): array
    {
        $input = $state['input'];
        $matches = $state['delimiter_matches'];
        $arms = [];
        $armStart = $start;
        $quote = null;
        $escaped = false;
        $inComment = false;
        $lineWhitespace = $start === 0 || $input[$start - 1] === "\n" || $input[$start - 1] === "\r";
        for ($i = $start; $i < $end; $i++) {
            $char = $input[$i];
            if ($inComment) {
                if ($char === "\n" || $char === "\r") { $inComment = false; $lineWhitespace = true; }
                continue;
            }
            if ($quote !== null) {
                if ($escaped) { $escaped = false; continue; }
                if ($char === '\\') { $escaped = true; continue; }
                if ($char === $quote) $quote = null;
                continue;
            }
            if ($char === "'" || $char === '"') { $quote = $char; continue; }
            if ($lineWhitespace && ($char === '#' || ($char === '/' && $i + 1 < $end && $input[$i + 1] === '/'))) {
                $inComment = true;
                continue;
            }
            if ($char === "\n" || $char === "\r") { $lineWhitespace = true; continue; }
            if (self::isWhitespace($char)) continue;
            $lineWhitespace = false;
            if (($char === '(' || $char === '[' || $char === '{') && isset($matches[$i]) && $matches[$i] < $end) {
                $i = $matches[$i];
                continue;
            }
            if ($char === ',' ) { $arms[] = [$armStart, $i]; $armStart = $i + 1; }
        }
        $arms[] = [$armStart, $end];
        return $arms;
    }

    private static function scanDelimited(array $state, int $open, int $end, string $opening, string $closing): ?int
    {
        $input = $state['input'];
        if ($input[$open] !== $opening || !isset($state['delimiter_matches'][$open])) return null;
        $close = $state['delimiter_matches'][$open];
        return $close < $end && $input[$close] === $closing ? $close : null;
    }

    private static function scanPermissiveDelimited(string $input, int $open, int $end, string $opening, string $closing): ?int
    {
        $stack = [$opening];
        for ($i = $open + 1; $i < $end; $i++) {
            $char = $input[$i];
            if ($char === '(' || $char === '[' || $char === '{') { $stack[] = $char; continue; }
            if ($char !== ')' && $char !== ']' && $char !== '}') continue;
            $expected = match (end($stack)) { '(' => ')', '[' => ']', '{' => '}', default => null };
            if ($char !== $expected) return null;
            array_pop($stack);
            if ($stack === []) return $i;
        }
        return null;
    }

    private static function hasMismatchedDelimiter(string $input, int $open, int $end): bool
    {
        $stack = [$input[$open]];
        $quote = null;
        $escaped = false;
        for ($i = $open + 1; $i < $end; $i++) {
            $char = $input[$i];
            if ($quote !== null) {
                if ($escaped) { $escaped = false; continue; }
                if ($char === '\\') { $escaped = true; continue; }
                if ($char === $quote) $quote = null;
                continue;
            }
            if ($char === "'" || $char === '"') { $quote = $char; continue; }
            if ($char === '(' || $char === '[' || $char === '{') { $stack[] = $char; continue; }
            if ($char !== ')' && $char !== ']' && $char !== '}') continue;
            $expected = match (end($stack)) { '(' => ')', '[' => ']', '{' => '}', default => null };
            if ($char !== $expected) return true;
            array_pop($stack);
            if ($stack === []) return false;
        }
        return false;
    }

    /** @return array<int,int> opening byte offset => matching closing byte offset */
    private static function buildDelimiterMap(string $input): array
    {
        $matches = [];
        $stack = [];
        $quote = null;
        $escaped = false;
        $inComment = false;
        $lineWhitespace = true;
        $length = strlen($input);
        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            if ($inComment) {
                if ($char === "\n" || $char === "\r") { $inComment = false; $lineWhitespace = true; }
                continue;
            }
            if ($quote !== null) {
                if ($escaped) { $escaped = false; continue; }
                if ($char === '\\') { $escaped = true; continue; }
                if ($char === $quote) $quote = null;
                continue;
            }
            if ($char === "'" || $char === '"') { $quote = $char; continue; }
            if ($lineWhitespace && ($char === '#' || ($char === '/' && $i + 1 < $length && $input[$i + 1] === '/'))) {
                $inComment = true;
                continue;
            }
            if ($char === "\n" || $char === "\r") { $lineWhitespace = true; continue; }
            if (self::isWhitespace($char)) continue;
            $lineWhitespace = false;
            if ($char === '(' || $char === '[' || $char === '{') { $stack[] = [$char, $i]; continue; }
            if ($char === ')' || $char === ']' || $char === '}') {
                $top = end($stack);
                $expected = match ($top[0] ?? null) { '(' => ')', '[' => ']', '{' => '}', default => null };
                if ($char !== $expected) continue;
                array_pop($stack);
                $matches[$top[1]] = $i;
            }
        }
        return $matches;
    }

    /** @return array{valid:bool,value:mixed} */
    private static function decodeJson(string $raw): array
    {
        try {
            return ['valid' => true, 'value' => json_decode($raw, true, 512, JSON_THROW_ON_ERROR)];
        } catch (\JsonException) {
            return ['valid' => false, 'value' => null];
        }
    }

    private static function scanQuoted(string $input, int $start, int $end, string $quote): ?int
    {
        $escaped = false;
        for ($i = $start + 1; $i < $end; $i++) {
            if ($escaped) { $escaped = false; continue; }
            if ($input[$i] === '\\') { $escaped = true; continue; }
            if ($input[$i] === $quote) return $i;
        }
        return null;
    }

    private static function nextTopLevelTagStart(string $input, int $start, int $end): ?int
    {
        for ($i = $start; $i < $end; $i++) {
            if ($input[$i] === '@' && self::isTagBoundaryBefore($input, $i)) return $i;
        }
        return null;
    }

    private static function isTagBoundaryBefore(string $input, int $position): bool { return $position === 0 || self::isWhitespace($input[$position - 1]); }
    private static function isWhitespace(string $char): bool { return $char === ' ' || $char === "\t" || $char === "\r" || $char === "\n"; }
    private static function isAsciiLetter(string $char): bool { return ($char >= 'A' && $char <= 'Z') || ($char >= 'a' && $char <= 'z'); }
    private static function isNameCharacter(string $char): bool { return self::isAsciiLetter($char) || ($char >= '0' && $char <= '9') || $char === '_' || $char === '-'; }
    private static function isUppercaseName(string $input, int $start, int $end): bool
    {
        for ($i = $start; $i < $end; $i++) {
            $char = $input[$i];
            if (self::isAsciiLetter($char) && !($char >= 'A' && $char <= 'Z')) return false;
        }
        return true;
    }
    private static function limit(mixed $value, int $maximum): int { return max(1, min((int) $value, $maximum)); }
    private static function diagnostic(string $code, string $severity, int $start, int $end, string $message): array { return compact('code', 'severity', 'start', 'end', 'message'); }
    private static function addDiagnostic(array &$state, string $code, string $severity, int $start, int $end, string $message): void { if ($state['mode'] === 'diagnostic') $state['diagnostics'][] = self::diagnostic($code, $severity, $start, $end, $message); }
    private static function candidate(array &$state, int $start, int $end, string $code, string $message): ?array
    {
        if ($state['mode'] !== 'diagnostic') return null;
        self::addDiagnostic($state, $code, 'warning', $start, $end, $message);
        return ['type' => 'candidate', 'start' => $start, 'end' => $end, 'raw' => substr($state['input'], $start, $end - $start)];
    }
}
