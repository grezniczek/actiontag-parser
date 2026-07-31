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
            $nodes = [];
            self::parseRange($state, 0, $state['length'], [], true, null, 0, $nodes);
            if ($mode === 'diagnostic') $result['nodes'] = $nodes;
        }

        $result['conditions'] = $state['conditions'];
        $result['tags'] = $state['tags'];
        if ($mode === 'diagnostic') $result['diagnostics'] = $state['diagnostics'];
        return $result;
    }

    /** @param array<string,mixed> $state @param list<array{id:int,negated:bool}> $conditional @param list<array> $nodes */
    private static function parseRange(array &$state, int $start, int $end, array $conditional, bool $inheritedEnabled, ?int $disabledBy, int $depth, array &$nodes): void
    {
        if ($depth > $state['max_nesting_depth']) {
            self::addDiagnostic($state, 'nesting_limit_exceeded', 'error', $start, $end, 'The configured nesting limit was exceeded.');
            return;
        }

        $input = $state['input'];
        $i = $start;
        $textStart = $start;
        while ($i < $end) {
            if ($input[$i] !== '@' || !self::isTagBoundaryBefore($input, $i)) {
                $i++;
                continue;
            }

            $candidateStart = $i;
            $explicitlyDisabled = false;
            $nameStart = $i + 1;
            if (substr($input, $i, 6) === '@.OFF.') {
                $explicitlyDisabled = true;
                $nameStart = $i + 6;
            }

            if ($nameStart >= $end || !self::isAsciiLetter($input[$nameStart])) {
                $candidate = self::candidate($state, $candidateStart, min($candidateStart + 1, $end), 'possible_action_tag', 'Action-tag candidate does not start with a valid name.');
                if ($candidate !== null) {
                    self::emitText($state, $nodes, $textStart, $candidateStart);
                    $nodes[] = $candidate;
                    $textStart = $candidate['end'];
                }
                $i++;
                continue;
            }

            $nameEnd = $nameStart + 1;
            while ($nameEnd < $end && self::isNameCharacter($input[$nameEnd])) $nameEnd++;
            $rawName = substr($input, $candidateStart, $nameEnd - $candidateStart);
            $name = '@' . strtoupper(substr($input, $nameStart, $nameEnd - $nameStart));

            $parameterStart = $nameEnd;
            while ($parameterStart < $end && self::isWhitespace($input[$parameterStart])) $parameterStart++;
            $introducer = $parameterStart < $end ? $input[$parameterStart] : '';
            $bareTag = $nameEnd === $end || (self::isWhitespace($input[$nameEnd]) && $introducer !== '=' && $introducer !== '(');

            if ($name === '@IF' && $introducer === '(') {
                self::emitText($state, $nodes, $textStart, $candidateStart);
                $after = self::parseIf($state, $candidateStart, $rawName, $explicitlyDisabled, $parameterStart, $end, $conditional, $inheritedEnabled, $disabledBy, $depth, $nodes);
                $i = max($after, $i + 1);
                $textStart = $i;
                continue;
            }

            if ($introducer === '=') {
                [$parameter, $after] = self::parseAssignment($state, $parameterStart, $end);
                if ($parameter === null) {
                    $i = max($after, $i + 1);
                    continue;
                }
                self::emitText($state, $nodes, $textStart, $candidateStart);
                self::emitTag($state, $nodes, $name, $rawName, $candidateStart, $after, $parameter, $conditional, $inheritedEnabled, $explicitlyDisabled, $disabledBy);
                $i = $after;
                $textStart = $i;
                continue;
            }
            if ($introducer === '(') {
                $close = self::scanDelimited($state, $parameterStart, $end, '(', ')');
                if ($close === null) {
                    self::addDiagnostic($state, 'unterminated_parenthesized_parameter', 'error', $candidateStart, $end, 'Parenthesized action-tag parameter is not closed.');
                    $i = $end;
                    continue;
                }
                self::emitText($state, $nodes, $textStart, $candidateStart);
                $parameter = [
                    'kind' => 'arguments',
                    'start' => $parameterStart,
                    'end' => $close + 1,
                    'raw' => substr($input, $parameterStart, $close - $parameterStart + 1),
                    'value' => substr($input, $parameterStart + 1, $close - $parameterStart - 1),
                ];
                self::emitTag($state, $nodes, $name, $rawName, $candidateStart, $close + 1, $parameter, $conditional, $inheritedEnabled, $explicitlyDisabled, $disabledBy);
                $i = $close + 1;
                $textStart = $i;
                continue;
            }
            if ($bareTag || $parameterStart === $end) {
                self::emitText($state, $nodes, $textStart, $candidateStart);
                self::emitTag($state, $nodes, $name, $rawName, $candidateStart, $nameEnd, null, $conditional, $inheritedEnabled, $explicitlyDisabled, $disabledBy);
                $i = $nameEnd;
                $textStart = $i;
                continue;
            }

            $candidate = self::candidate($state, $candidateStart, $nameEnd, 'invalid_tag_name', 'Action-tag name is not followed by a valid boundary or parameter introducer.');
            if ($candidate !== null) {
                self::emitText($state, $nodes, $textStart, $candidateStart);
                $nodes[] = $candidate;
                $textStart = $candidate['end'];
            }
            $i = $nameEnd;
        }
        self::emitText($state, $nodes, $textStart, $end);
    }

    /** @return array{0:?array,1:int} */
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

    /** @param list<array> $nodes @param list<array{id:int,negated:bool}> $conditional */
    private static function parseIf(array &$state, int $start, string $rawName, bool $explicitlyDisabled, int $open, int $end, array $conditional, bool $inheritedEnabled, ?int $disabledBy, int $depth, array &$nodes): int
    {
        $input = $state['input'];
        $close = self::scanDelimited($state, $open, $end, '(', ')');
        if ($close === null) {
            self::addDiagnostic($state, 'unterminated_if', 'error', $start, $end, '@IF does not reach its closing parenthesis.');
            return $end;
        }
        if ($state['mode'] === 'fast' && $explicitlyDisabled) return $close + 1;

        $arms = self::splitIfArms($state, $open + 1, $close);
        if (count($arms) > 3) {
            self::addDiagnostic($state, 'if_unexpected_top_level_separator', 'error', $start, $close + 1, '@IF has more than three top-level arguments.');
            return $close + 1;
        }
        if (count($arms) < 2 || trim(substr($input, $arms[0][0], $arms[0][1] - $arms[0][0])) === '') {
            self::addDiagnostic($state, 'if_missing_condition', 'error', $start, $close + 1, '@IF requires a condition and a true branch.');
            return $close + 1;
        }
        if (trim(substr($input, $arms[1][0], $arms[1][1] - $arms[1][0])) === '') {
            self::addDiagnostic($state, 'if_missing_then', 'error', $start, $close + 1, '@IF requires a true branch.');
            return $close + 1;
        }
        if (count($arms) === 2 && !self::ACCEPT_IF_THEN_SHORTHAND) {
            self::addDiagnostic($state, 'if_missing_else', 'error', $start, $close + 1, '@IF requires a false branch.');
            return $close + 1;
        }
        if (count($arms) === 2) $arms[] = [$close, $close];

        $conditionId = $state['next_condition_id']++;
        [$conditionStart, $conditionEnd] = $arms[0];
        $state['conditions'][$conditionId] = [
            'raw' => substr($input, $conditionStart, $conditionEnd - $conditionStart),
            'start' => $conditionStart,
            'end' => $conditionEnd,
        ];
        $enabled = $inheritedEnabled && !$explicitlyDisabled;
        $ifNode = [
            'type' => 'if', 'name' => '@IF', 'raw_name' => $rawName,
            'start' => $start, 'end' => $close + 1, 'raw' => substr($input, $start, $close - $start + 1),
            'enabled' => $enabled, 'explicitly_disabled' => $explicitlyDisabled,
            'conditional' => $conditional, 'condition_id' => $conditionId,
            'then' => [], 'else' => [],
        ];
        if (!$enabled && $disabledBy !== null) $ifNode['disabled_by'] = $disabledBy;

        $thenConditional = [...$conditional, ['id' => $conditionId, 'negated' => false]];
        $elseConditional = [...$conditional, ['id' => $conditionId, 'negated' => true]];
        $childDisabledBy = $explicitlyDisabled ? $conditionId : $disabledBy;
        self::parseRange($state, $arms[1][0], $arms[1][1], $thenConditional, $enabled, $childDisabledBy, $depth + 1, $ifNode['then']);
        self::parseRange($state, $arms[2][0], $arms[2][1], $elseConditional, $enabled, $childDisabledBy, $depth + 1, $ifNode['else']);
        if ($state['mode'] === 'diagnostic') $nodes[] = $ifNode;
        return $close + 1;
    }

    /** @param list<array> $nodes @param list<array{id:int,negated:bool}> $conditional */
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

    /** @return list<array{int,int}> */
    private static function splitIfArms(array $state, int $start, int $end): array
    {
        $input = $state['input'];
        $matches = $state['delimiter_matches'];
        $arms = [];
        $armStart = $start;
        $quote = null;
        $escaped = false;
        for ($i = $start; $i < $end; $i++) {
            $char = $input[$i];
            if ($quote !== null) {
                if ($escaped) { $escaped = false; continue; }
                if ($char === '\\') { $escaped = true; continue; }
                if ($char === $quote) $quote = null;
                continue;
            }
            if ($char === "'" || $char === '"') { $quote = $char; continue; }
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

    /** @return array<int,int> opening byte offset => matching closing byte offset */
    private static function buildDelimiterMap(string $input): array
    {
        $matches = [];
        $stack = [];
        $quote = null;
        $escaped = false;
        $length = strlen($input);
        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];
            if ($quote !== null) {
                if ($escaped) { $escaped = false; continue; }
                if ($char === '\\') { $escaped = true; continue; }
                if ($char === $quote) $quote = null;
                continue;
            }
            if ($char === "'" || $char === '"') { $quote = $char; continue; }
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
