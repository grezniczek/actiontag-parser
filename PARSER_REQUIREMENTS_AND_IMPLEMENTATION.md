# Action Tag Parser: Requirements and Initial Implementation Design

## Status and Scope

This is the first implementation-design draft for the parser to be built in this External Module. It turns the core-integration plan into an implementable contract, but it deliberately does not prescribe semantic rules for individual action tags.

The parser will be portable PHP: it may not read REDCap metadata, access a database, use globals, evaluate logic or piping, or depend on the External Module framework. Its only input is annotation text plus parser options. Its only output is structured parse data and, in diagnostic mode, syntax findings.

This document is intentionally written so that the resulting parser can later move unchanged to REDCap core as `Classes/ActionTagParser.php`.

## Decisions Made So Far

- Use a hand-written deterministic state machine, not a collection of extraction regular expressions.
- Support a `fast` mode and a `diagnostic` mode from the same grammar.
- Use byte offsets as the canonical source location. Line and column are diagnostic-mode presentation data.
- Keep tag semantics out of the parser, except that `@IF` has defined container syntax.
- Parse `@IF` recursively. It does not evaluate or syntax-check its logic expression.
- In fast mode, emit action tags from `@IF` branches as flattened tags with an effective `conditional` expression; do not emit `@IF` itself.
- In diagnostic mode, retain every valid `@IF` as a container node while also making its nested action tags available with their effective conditionals.
- Preserve original spelling and raw parameter/condition text; normalize tag names for lookup.

## Terminology

| Term | Meaning |
| --- | --- |
| Candidate | Text beginning with `@` that might be an action tag but is incomplete or malformed. Candidates are relevant only to diagnostic output. |
| Tag node | A recognized non-`@IF` action tag, with its parameter syntax and source range. It is not necessarily a known or valid tag semantically. |
| `@IF` node | A recognized `@IF` container, with opaque condition text and parsed true/false branches. It is retained only in the diagnostic tree. |
| Effective conditional | The conjunction of every `@IF` branch that encloses a tag. It states when that tag's branch applies, but is never evaluated by the parser. |
| Raw text | Exact substring from the source range, including meaningful whitespace. |
| Normalized name | The case-normalized action-tag name used for comparison, initially uppercase. |

## Input and Options

The eventual public facade is expected to be equivalent to:

```php
ActionTagParser::parse(string $annotation, array $options = []): array
```

The class method name and visibility can be finalized during implementation, but the input/output behavior below is the intended contract.

| Option | Default | Meaning |
| --- | --- | --- |
| `mode` | `fast` | `fast` emits usable valid tags with minimal materialization; `diagnostic` emits the structural tree and findings. |
| `include_text_segments` | `false` | In diagnostic mode, include source text nodes between parsed candidates for editor consumers that need a complete annotation representation. |
| `max_input_bytes` | Safe implementation default | Maximum input accepted for one parse. A limit breach is a diagnostic finding; fast mode returns no untrusted partial construct. |
| `max_nesting_depth` | Safe implementation default | Maximum nested delimiter/`@IF` depth. Prevents resource exhaustion from adversarial input. |

The exact limit values should be selected from fixture and benchmark evidence. Limits must be explicit constants/defaults, documented, and overrideable only within safe upper bounds.

## Supported General Syntax

The parser recognizes action-tag syntax independently of a tag catalog. The provisional candidate name grammar is a compatibility-oriented superset:

```text
@ name
name := ASCII-letter (ASCII-letter | digit | '_' | '-')*
```

Names retain their original source spelling and are normalized case-insensitively. Boundary rules must prevent incidental text such as email addresses from becoming tags while preserving current valid annotation forms. The final boundary matrix will be fixture-driven from native REDCap and External Module examples; it is not a semantic whitelist.

Supported parameter shapes are:

- no parameter: `@HIDDEN`;
- assignment: `@DEFAULT='value'`, `@TAG=value`, and the compatible quoted/unquoted variants;
- JSON assignment values, including balanced object and array structures; and
- parenthesized arguments: `@TAG(...)`, respecting nested delimiters, quoted strings, and escapes.

Whitespace between a tag name and its introducer (`=` or `(`) is accepted where existing REDCap behavior accepts it, notably `@IF (...)`. Parameter syntax is captured structurally; whether it is allowed for a particular tag is a validator concern.

Legacy deactivation notation, including `@.OFF.TAG` and the deactivated-tag marker, is recognized as a deactivated representation rather than as a malformed tag. Its compatibility details belong in the test corpus.

## `@IF` Structural Syntax

`@IF` is identified by normalized name, so source casing does not change behavior. It is a syntactic container, not an ordinary parenthesized parameter.

The existing core behavior establishes the canonical form:

```text
@IF(condition, then-annotation, else-annotation)
```

The parser must find the two separators only at the `@IF` argument's top level. Commas inside quoted strings, JSON-like values, balanced parentheses, or nested `@IF` constructs do not split the three arguments. The parser retains all three raw argument ranges.

The direct condition is opaque:

- preserve its text and source range;
- do not call `REDCap::evaluateLogic`, `Form::replaceIfActionTag`, piping, or a logic parser;
- do not diagnose invalid field references, operators, quoting, or logical precedence; and
- only diagnose the `@IF` wrapper's own structural failures, such as missing delimiter or closing parenthesis.

The `then` and `else` ranges are annotation fragments. They are recursively parsed for tags and nested `@IF` containers. A future compatibility decision may explicitly add a two-argument `@IF` form if core behavior warrants it; it must not be accepted accidentally as a malformed three-argument form.

## Conditional Inheritance

Each non-`@IF` tag has a `conditional` entry:

- `null` when it is not inside an `@IF` branch;
- otherwise a canonical string formed from its enclosing branch conditions.

For a true branch, append the direct condition. For a false branch, append the negation of the direct condition. Each expression is parenthesized before composition, preserving precedence without attempting to understand the expression:

```text
@IF([a] = '1', @HIDDEN, @READONLY)

@HIDDEN   => conditional: "([a] = '1')"
@READONLY => conditional: "(not ([a] = '1'))"
```

For nesting, append the current branch expression with `and`:

```text
@IF([a] = '1', @IF([b] = '2', @HIDDEN, @READONLY), @REQUIRED)

@HIDDEN   => "([a] = '1') and ([b] = '2')"
@READONLY => "([a] = '1') and (not ([b] = '2'))"
@REQUIRED => "(not ([a] = '1'))"
```

The parser should retain a diagnostic-only `conditional_stack` alongside the convenience string. Each entry contains the original condition range/text and branch polarity. This preserves provenance and lets a later consumer render or transform the condition without reverse-parsing the concatenated string.

An `@IF` node's own `conditional` describes the inherited context in which the container itself occurs; its `condition` describes its direct opaque test. Thus a nested `@IF` is visible as conditionally present even before its branch conditions are applied.

## Result Contract

The exact PHP array keys are part of the public contract once implementation begins. The following is the first proposed version.

### Shared tag-node fields

```php
[
    'type'            => 'tag',
    'name'            => '@HIDDEN',          // normalized
    'raw_name'        => '@hidden',          // source spelling
    'start'           => 24,                 // inclusive byte offset
    'end'             => 30,                 // exclusive byte offset
    'raw'             => '@hidden',
    'deactivated'     => false,
    'parameter'       => null,               // or the parameter structure below
    'conditional'     => "([status] = '1')", // null when unconditional
]
```

`parameter`, when present, includes a `kind` (`assignment`, `quoted`, `unquoted`, `json`, or `arguments` as applicable), its raw source range/text, and parsed value data only where parsing is unambiguous. JSON decoding failure is reported as a diagnostic; the raw parameter is still retained.

### Fast-mode result

```php
[
    'mode' => 'fast',
    'tags' => [ /* flattened non-@IF tag nodes in source order */ ],
]
```

Fast mode omits text nodes, `@IF` containers, candidate nodes, detailed recovery state, line/column data, and detailed diagnostics. A valid tag nested in a valid `@IF` is still included in `tags` with its effective `conditional`. A malformed `@IF` does not yield trusted flattened children in fast mode.

### Diagnostic-mode result

```php
[
    'mode'        => 'diagnostic',
    'nodes'       => [ /* root tag, @IF, and candidate nodes in source order */ ],
    'tags'        => [ /* flattened non-@IF valid tag nodes in source order */ ],
    'diagnostics' => [ /* ordered syntax findings */ ],
]
```

`nodes` contains every valid `@IF` as a container node and, when useful for user feedback, malformed candidate nodes. With `include_text_segments`, it also contains text nodes that cover the remaining source. `tags` remains a convenient flattened view; it never includes `@IF` wrappers.

A diagnostic `@IF` node is proposed to have this shape:

```php
[
    'type'              => 'if',
    'name'              => '@IF',
    'raw_name'          => '@IF',
    'start'             => 0,
    'end'               => 53,
    'raw'               => "@IF([a] = '1', @HIDDEN, @READONLY)",
    'conditional'       => null,             // inherited context only
    'conditional_stack' => [],
    'condition'         => [
        'raw'   => "[a] = '1'",
        'start' => 4,
        'end'   => 13,
    ],
    'then'              => [ /* recursive nodes */ ],
    'else'              => [ /* recursive nodes */ ],
]
```

For a false branch, the child `conditional_stack` records a negative polarity rather than altering the raw condition. The canonical `conditional` string is derived from that stack.

## Diagnostics and Recovery

Diagnostic mode reports syntax observations with at least:

```php
[
    'code'     => 'unterminated_if',
    'severity' => 'error', // error, warning, or info
    'start'    => 0,
    'end'      => 19,
    'message'  => '...',   // optional, non-localized fallback text
]
```

Line and column can be added lazily by a presentation helper or included in diagnostic mode after the primary scan. Byte offsets remain authoritative.

The initial diagnostic-code set should include:

| Code | Meaning |
| --- | --- |
| `possible_action_tag` | `@` starts text that resembles a tag but cannot be confirmed. |
| `invalid_tag_name` | A tag candidate violates the accepted name form. |
| `unterminated_quoted_parameter` | A quoted parameter reaches its recovery boundary without a matching quote. |
| `unterminated_parenthesized_parameter` | A parenthesized argument does not close. |
| `unbalanced_parameter_delimiter` | An unexpected closing delimiter or impossible nesting state occurs. |
| `invalid_json_parameter` | A JSON-shaped parameter is structurally bounded but cannot be decoded as JSON. |
| `unterminated_if` | `@IF(` does not reach its matching closing parenthesis. |
| `if_missing_condition` | An `@IF` condition arm is empty. |
| `if_missing_then` | An `@IF` true branch is absent. |
| `if_missing_else` | An `@IF` false branch is absent under the canonical three-arm grammar. |
| `if_unexpected_top_level_separator` | `@IF` contains too many top-level argument separators. |
| `nesting_limit_exceeded` | Configured delimiter or `@IF` depth is exceeded. |
| `input_limit_exceeded` | Configured input-size limit is exceeded. |

Recovery must be deterministic. After a malformed ordinary candidate, resume at a point that allows a following top-level tag to be found. For an unterminated `@IF` in diagnostic mode, the parser may parse a clearly located branch provisionally, but every such child must be marked as recovered/possible and must not enter the fast-mode flattened result. If the branches cannot be located unambiguously, report the container error and resume at the next safe top-level boundary.

## State-Machine Design

### Design goal

The parser must be linear in input size, including deeply nested `@IF` expressions. A design that first scans an entire `@IF` body and then recursively reparses its substrings can rescan the same source at each nesting level and degrade toward O(n²). The implementation must avoid that pattern.

### Scanner frames

Use one forward byte scanner with an explicit stack of frames. Relevant frame/state data includes:

- current input index and active source range;
- normal annotation scan, tag-name scan, assignment scan, quoted-value scan, JSON/balanced-value scan, and parenthesized-argument scan states;
- quote character and escape state;
- delimiter-depth counters/stack;
- current conditional stack;
- an `@IF` frame containing the direct condition range, current arm (`condition`, `then`, or `else`), top-level separator count, and diagnostic node being built; and
- parent node/sink references appropriate to the selected mode.

When an `@IF` opens, push an `@IF` frame. While scanning its condition, only a comma at that frame's top level completes the condition. Once the condition is complete, derive the true-branch conditional stack and scan branch content normally. The second top-level comma switches to the false branch, whose stack has negative polarity. The matching close parenthesis completes the frame and returns control to the parent annotation context.

Nested `@IF` frames use the current branch's stack, so conditions are inherited without a later tree walk or reparsing. In fast mode, frames retain only the minimum metadata necessary to propagate conditions and emit leaf tags. In diagnostic mode, they also retain the container and branch nodes.

### Delimiter and quote handling

Every state that can contain structured text must observe:

- single- and double-quoted strings;
- backslash escapes inside quoted strings where the relevant syntax permits them;
- balanced `()`, `[]`, and `{}` delimiters as required for the active parameter/`@IF` context; and
- commas only when the active `@IF` frame considers them top-level separators.

The parser need not understand REDCap logic inside an `@IF` condition. It only balances enough structure to locate its argument boundaries safely. This is structural recognition, not logic validation.

### Output sinks

Use a mode-specific emission sink rather than maintaining two parsers:

- **Fast sink:** append valid non-`@IF` nodes to `tags`; discard containers and detailed recovery records.
- **Diagnostic sink:** append root/branch nodes, retain `@IF` containers and candidates, append diagnostics, and optionally include text segments.

Shared scanning and node-construction helpers guarantee that a valid leaf tag has the same name, range, parameter, and `conditional` in both modes.

## Performance Requirements

- One forward scan in the normal case; no repeated parsing of nested `@IF` source ranges.
- O(n) time and O(n) output memory, with stack space bounded by `max_nesting_depth`.
- Fast preflight: return an empty tag list immediately when the input has no `@` byte.
- No broad/nested PCRE in the hot path. Small, anchored checks may be considered only if benchmarks demonstrate bounded behavior, but byte classification is preferred.
- Build strings from source offsets instead of per-character arrays where possible.
- Compute line/column locations only for diagnostics that will be returned.
- Keep caching outside the parser; callers may cache results using annotation text and parser-version/options as keys.

Benchmarks must include ordinary annotation text, many independent tags, JSON parameters, quoted values containing delimiters, nested `@IF` containers, and deliberately deep malformed inputs. The acceptance criterion is predictable linear growth rather than only a single absolute runtime number.

## Test Corpus and Acceptance Cases

The first fixture suite must cover at least:

1. Native and External Module bare tags, mixed casing, hyphen/underscore names, and names with digits where currently used.
2. Tags with quoted, unquoted, JSON, and parenthesized parameters, including delimiters inside quotes and escaped quotes.
3. Multiple tags and literal/candidate `@` text, including email-like strings that must not become tags.
4. Legacy deactivation forms and action tags adjacent to annotation text under supported boundary rules.
5. A canonical true/false `@IF`, an `@IF` containing multiple tags per branch, and `@IF` inside each branch of another `@IF`.
6. Exact effective `conditional` and `conditional_stack` values for every nested branch combination.
7. Diagnostic-tree retention of all valid `@IF` nodes, contrasted with their absence from fast-mode `tags`.
8. Invalid `@IF` wrappers: missing comma, missing arm, extra top-level comma, unclosed outer parenthesis, and quotes/parentheses within the opaque condition.
9. Recovery after malformed ordinary tags and malformed `@IF` constructs, with a following valid tag still located.
10. Limit and performance tests for deeply nested, oversized, and adversarial inputs.

Before a core move, fixtures must also record any intentional behavioral differences from legacy `Form`/`ActionTags` helpers. The parser's role is a stable structural standard, not an undocumented emulation of every historical regex quirk.

## Explicit Non-Goals

- Evaluating an `@IF` condition or deciding which branch is active.
- Validating REDCap logic syntax or field references inside a condition.
- Validating that a tag exists, is enabled, supports a parameter kind, or is legal for a field/context.
- Resolving piping, record values, project metadata, or External Module configuration.
- Replacing `Form::replaceIfActionTag()` or legacy helper behavior in the same change that introduces this parser.

Those responsibilities remain with core runtime code and the future semantic action-tag validator. The parser supplies the source-faithful structure those layers need.
