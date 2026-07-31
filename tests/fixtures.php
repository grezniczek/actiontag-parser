<?php

/**
 * Contract fixtures for the portable parser.  Assertions intentionally focus
 * on the stable public shape rather than implementation details.
 */
return [
    'bare_names_and_boundaries' => [
        'annotation' => "@hidden \t@my_tag-2\nemail@example.test @NOPE! @LAST",
        'mode' => 'fast',
        'tag_names' => ['@HIDDEN', '@MY_TAG-2', '@LAST'],
        'tag_ranges' => [[0, 7], [9, 18], [45, 50]],
    ],
    'parameter_forms' => [
        'annotation' => "@DEFAULT='a\\'b' @TAG=value @JSON={\"a\":[1,2]} @ARRAY=[1,2] @QUOTED='[1,2]' @ARGS(one, 'two, three')",
        'mode' => 'diagnostic',
        'tag_names' => ['@DEFAULT', '@TAG', '@JSON', '@ARRAY', '@QUOTED', '@ARGS'],
        'parameter_kinds' => ['quoted', 'unquoted', 'json', 'json', 'quoted', 'arguments'],
        'diagnostic_codes' => ['deprecated_unquoted_parameter'],
    ],
    'json_compatibility' => [
        'annotation' => "@OBJECT='{\"key\":\"value\"}' @QUOTED='[1,2]' @BAD=[whatever]",
        'mode' => 'diagnostic',
        'tag_names' => ['@OBJECT', '@QUOTED', '@BAD'],
        'parameter_kinds' => ['json', 'quoted', 'unquoted'],
        'diagnostic_codes' => [
            'deprecated_single_quoted_json_object_parameter',
            'deprecated_unquoted_parameter',
        ],
    ],
    'disabled_tag' => [
        'annotation' => '@.OFF.HIDDEN @READONLY',
        'mode' => 'diagnostic',
        'tag_names' => ['@HIDDEN', '@READONLY'],
        'enabled' => [false, true],
        'explicitly_disabled' => [true, false],
    ],
    'if_flattening_and_conditions' => [
        'annotation' => "@IF([a] = '1', @IF([b] = '2', @HIDDEN, @READONLY), @REQUIRED)",
        'mode' => 'fast',
        'tag_names' => ['@HIDDEN', '@READONLY', '@REQUIRED'],
        'conditions' => ["[a] = '1'", "[b] = '2'"],
        'conditional' => [
            [[1, false], [2, false]],
            [[1, false], [2, true]],
            [[1, true]],
        ],
    ],
    'if_diagnostic_tree_and_empty_else' => [
        'annotation' => "@IF([a] = '1', @HIDDEN, '')",
        'mode' => 'diagnostic',
        'tag_names' => ['@HIDDEN'],
        'conditions' => ["[a] = '1'"],
        'node_types' => ['if'],
        'diagnostic_codes' => [],
    ],
    'disabled_if' => [
        'annotation' => '@.OFF.IF([a], @HIDDEN, @READONLY) @REQUIRED',
        'mode' => 'fast',
        'tag_names' => ['@REQUIRED'],
        'conditions' => [],
    ],
    'disabled_if_diagnostic' => [
        'annotation' => '@.OFF.IF([a], @HIDDEN, @READONLY)',
        'mode' => 'diagnostic',
        'tag_names' => ['@HIDDEN', '@READONLY'],
        'enabled' => [false, false],
        'node_types' => ['if'],
    ],
    'invalid_if_and_recovery' => [
        'annotation' => '@IF([a], @HIDDEN) @READONLY',
        'mode' => 'diagnostic',
        'tag_names' => ['@READONLY'],
        'diagnostic_codes' => ['if_missing_else'],
    ],
    'diagnostic_text_segments' => [
        'annotation' => 'Before @HIDDEN after',
        'mode' => 'diagnostic',
        'options' => ['include_text_segments' => true],
        'tag_names' => ['@HIDDEN'],
        'node_types' => ['text', 'tag', 'text'],
        'diagnostic_codes' => [],
    ],
    'ordinary_candidate_recovery' => [
        'annotation' => '@BROKEN! @READONLY',
        'mode' => 'diagnostic',
        'tag_names' => ['@READONLY'],
        'node_types' => ['candidate', 'tag'],
        'diagnostic_codes' => ['invalid_tag_name'],
    ],
    'unterminated_quote_recovery' => [
        'annotation' => "@DEFAULT='unfinished @READONLY",
        'mode' => 'diagnostic',
        'tag_names' => ['@READONLY'],
        'diagnostic_codes' => ['unterminated_quoted_parameter'],
    ],
    'input_limit' => [
        'annotation' => '@HIDDEN',
        'mode' => 'diagnostic',
        'options' => ['max_input_bytes' => 3],
        'tag_names' => [],
        'diagnostic_codes' => ['input_limit_exceeded'],
    ],
    'if_structural_diagnostics' => [
        'annotation' => '@IF([a], @HIDDEN, @READONLY, @REQUIRED) @LAST',
        'mode' => 'diagnostic',
        'tag_names' => ['@LAST'],
        'diagnostic_codes' => ['if_unexpected_top_level_separator'],
    ],
    'if_missing_arms' => [
        'annotation' => '@IF(, @HIDDEN, @READONLY) @IF([a], , @READONLY) @LAST',
        'mode' => 'diagnostic',
        'tag_names' => ['@LAST'],
        'diagnostic_codes' => ['if_missing_condition', 'if_missing_then'],
    ],
    'opaque_condition_structure' => [
        'annotation' => "@IF((func('x,y') = [a]), @HIDDEN, @READONLY)",
        'mode' => 'fast',
        'tag_names' => ['@HIDDEN', '@READONLY'],
        'conditions' => ["(func('x,y') = [a])"],
        'conditional' => [[[1, false]], [[1, true]]],
    ],
    'nested_if_in_both_branches' => [
        'annotation' => '@IF([a], @IF([b], @HIDDEN, @READONLY), @IF([c], @REQUIRED, @HIDDEN-FORM))',
        'mode' => 'fast',
        'tag_names' => ['@HIDDEN', '@READONLY', '@REQUIRED', '@HIDDEN-FORM'],
        'conditions' => ['[a]', '[b]', '[c]'],
        'conditional' => [
            [[1, false], [2, false]], [[1, false], [2, true]],
            [[1, true], [3, false]], [[1, true], [3, true]],
        ],
    ],
    'if_line_comments_and_invalid_json' => [
        'annotation' => "Some text\n\n@IF([record-name]=\"1 // ,\" and\n\t// This is a comment with a )\n2=2\n, @READONLY\n  // another comment with a comma ,\n,\n'')\nSome explanatory text.\n\n@JSON-LIST-ACTIONTAG=[{\"a\": \"b\"},{\"a\": c\"}]\n\nSome more text at end.",
        'mode' => 'diagnostic',
        'tag_names' => ['@READONLY', '@JSON-LIST-ACTIONTAG'],
        'parameter_kinds' => [null, 'unquoted'],
        'conditions' => ["[record-name]=\"1 // ,\" and\n\t// This is a comment with a )\n2=2\n"],
        'conditional' => [[[1, false]], []],
        'diagnostic_codes' => ['deprecated_unquoted_parameter'],
    ],
];
