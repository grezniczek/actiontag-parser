# Action Tag Parser: Requirements, Contract, and Implementation History

## Status and Scope

This document began as the implementation design for the External Module. The
portable parser was subsequently implemented in the EM and moved to REDCap's
`authoring-syntax-diagnostics` branch as `Classes/ActionTagParser.php`. It
still records the design constraints and acceptance goals, but the core
executable contract and shared PHP/JS fixtures now define the implemented
action-tag result shape.

The parser is portable PHP 8.1+: it may not read REDCap metadata, access a database, use globals, evaluate logic or piping, or depend on the External Module framework. Its only input is annotation text plus parser options. Its only output is structured parse data and, in diagnostic mode, syntax findings.

The parser is now present in core. The EM remains the experimentation and
benchmark environment for parser products, condition resolution, scoped
metadata helpers, and eventual compatibility work. Runtime replacement and a
documented `REDCap` facade remain separate future decisions.

### Contract ownership

- `UnitTests/ActionTags/ActionTagParserContract.md` in core and its PHP/JS
  fixtures are the current syntax-contract reference.
- This document owns the rationale, constraints, benchmark expectations, and
  migration goals that are broader than the pure parser result.
- `ActionTagParser::parse()` is currently an internal core surface. A future
  documented `REDCap::parseActionTags()` wrapper is the public stability
  commitment; it is not required for internal experimentation.

### Current Piping semantic evidence

Piping authoring diagnostics remain a semantic layer above the pure parser and
must be grounded in the precise `Piping::pipeSpecialTags()` runtime branch.
For recipient-aware bulk Survey Invitations, every selected recipient supplies
a survey participant ID, while record, event, and form context can be
guaranteed, partial, or unavailable. The catalog therefore permits bare,
unqualified `[instrument-name]`, `[instrument-label]`, and `[survey-title]`
when that participant context is present: the runtime derives the current form
from the participant before returning those values. This is not a general
recordless-form rule. Event or instance qualifiers remain unsupported, and an
explicit `survey-title` instrument parameter remains its independently
cataloged metadata-target route rather than a participant fallback.

### Field-embedding host evidence

Field embedding is available only from explicit editor actions on host
surfaces that the runtime processes with
`Piping::replaceEmbedVariablesInLabel()`: field labels (including ordinary
Section Header fields), matrix Section Headers, Field Notes, and individual
Choice Labels. Matrix headers use the rich-text source path. Choice Labels use
the `filter_tags()`-compatible one-line path: existing `<br>` tags are
preserved, and any new line break is explicitly serialized as either a space
or `<br>`. Do not infer this permission for other piping-capable text.

The pure Field Embedding parser remains syntax-only. Its separate PHP/browser
semantic analyzer mirrors `doFieldEmbedding()` using the draft-aware authoring
catalog: a named field must exist, cannot be the record ID, must be on the
host instrument and (for a paginated survey) its current page, cannot embed
itself or a field that already contains an embedding, and can appear only once
on the rendered form or survey page. The catalog records existing occurrences
by host metadata attribute; Choice Label occurrences also carry their choice
code, so updating one choice does not incorrectly free an embed in another.
The Online Designer's shared Field Label control must identify a stand-alone
section-header edit as that field's `element_preceding_header` and use `sq_id`
as its host, because the legacy UI deliberately clears `field_name` in that
mode. Thus the edited source alone replaces its own stored occurrence, while a
same-field label/header occurrence, or every other occurrence, still blocks
completion and receives a diagnostic. The catalog cache must be refreshed
after both full and incremental Online Designer metadata saves; otherwise a
newly saved section header can leave later Field Label checks with stale page
placement or occurrence data. Each Field Embedding editor open also obtains a
fresh catalog, protecting the ordinary Field Label path if another metadata
change did not trigger a Designer refresh; its request must bypass browser
HTTP caching. In an HTML source, a malformed Piping candidate in one text node
(such as a reference split by formatting markup) must not suppress Field
Embedding diagnostics for a complete curly-brace reference in another text
node. The server fallback uses the same analyzer and scans only ordinary HTML
text nodes.

## Decisions Made So Far

- Use a hand-written deterministic state machine, not a collection of extraction regular expressions.
- Support a `fast` mode and a `diagnostic` mode from the same grammar.
- Use byte offsets as the canonical source location. Line and column are diagnostic-mode presentation data.
- Keep tag semantics out of the parser, except that `@IF` has defined container syntax.
- Parse `@IF` recursively. It does not evaluate or syntax-check its logic expression.
- In fast mode, emit enabled action tags from `@IF` branches as flattened tags with structured conditional references; do not emit `@IF` itself.
- In diagnostic mode, retain every valid `@IF` as a container node while also making its nested action tags available with their effective conditional references.
- Treat `@.OFF.TAG` as syntactically deactivated. Fast mode omits it; diagnostic mode exposes it with its normalized name and `enabled: false`.
- Preserve original spelling and raw parameter/condition text. Action-tag names must use uppercase ASCII letters; lowercase candidates are diagnostic-only and are never normalized into tags. The sole name normalization converts underscores to dashes.

## Terminology

| Term | Meaning |
| --- | --- |
| Candidate | Text beginning with `@` that might be an action tag but is incomplete or malformed. Candidates are relevant only to diagnostic output. |
| Tag node | A recognized non-`@IF` action tag, with its parameter syntax and source range. It is not necessarily a known or valid tag semantically. |
| `@IF` node | A recognized `@IF` container, with opaque condition text and parsed true/false branches. It is retained only in the diagnostic tree. |
| Condition definition | One direct, opaque condition from an `@IF` occurrence, stored once in the top-level `conditions` map under a source-order numeric ID. |
| Conditional reference | An ordered `{id, negated}` reference to a condition definition. The ordered list on a tag is an implicit conjunction. |
| Effective conditional | The ordered conditional-reference list of every `@IF` branch that encloses a tag. It states when that tag's branch applies, but is never evaluated by the parser. |
| Explicitly disabled | A tag or `@IF` whose own source spelling uses the core `@.OFF.` prefix. |
| Effectively enabled | Whether a construct is neither explicitly disabled nor inside an explicitly disabled `@IF`. |
| Raw text | Exact substring from the source range, including meaningful whitespace. |
| Normalized name | The uppercase action-tag name used for comparison, with underscores converted to dashes. Lowercase source spelling is not an action tag. |

## Input and Options

The internal core parser contract is:

```php
ActionTagParser::parse(string $annotation, array $options = []): array
```

The class method name and visibility remain internal implementation details. A future, documented `REDCap::parseActionTags()` facade should delegate to this contract and preserve its input/output behavior.

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
name := ASCII-uppercase-letter (ASCII-uppercase-letter | digit | '_' | '-')*
```

Names must use uppercase ASCII letters; digits, underscores, and hyphens remain supported within the name. A lowercase candidate is not an action tag: fast mode ignores it and diagnostic mode reports it without normalizing its spelling. Underscores are accepted for compatibility but normalized to dashes in the emitted name; diagnostic mode emits `deprecated_underscore_action_tag_name`. An action tag may begin only at the start of the annotation or immediately after ASCII whitespace (space, tab, carriage return, or line feed). A bare tag name must end at annotation end or before ASCII whitespace. `=` and `(` may directly follow a tag name as parameter introducers; they are not tag separators. These explicit boundary rules prevent incidental text such as email addresses from becoming tags.

Supported parameter shapes are:

- no parameter: `@HIDDEN`;
- assignment: `@DEFAULT='value'`, `@TAG=value`, and the compatible quoted/unquoted variants;
- JSON assignment values, including balanced object and array structures; and
- parenthesized arguments: `@TAG(...)`, respecting nested delimiters, quoted strings, and escapes.

Whitespace between a tag name and its introducer (`=` or `(`) is accepted where existing REDCap behavior accepts it, notably `@IF (...)`. Parameter syntax is captured structurally; whether it is allowed for a particular tag is a validator concern.

Unquoted assignment values remain accepted for compatibility, but diagnostic mode emits the `deprecated_unquoted_parameter` warning. An unquoted value that begins with `[` or `{` is classified as JSON only when its complete parameter text successfully decodes as JSON. Otherwise it remains an unquoted string. Thus `@TAG=[1,2]` is a JSON array, while `@TAG=[whatever]` is an unquoted string; the parser does not treat JSON-like text as invalid merely because it fails to decode.

Single-quoted JSON **object** wrappers are also accepted for compatibility, for example `@TAG='{"key":"value"}'`. A single-quoted value is classified as JSON only when its content begins with `{` and successfully decodes to an object; diagnostic mode then emits `deprecated_single_quoted_json_object_parameter`. All other quoted values are strings, including `@TAG='[1,2]'`, `@TAG='[whatever]'`, and double-quoted JSON-looking text.

## Core Deactivation Syntax

Core deactivation notation, including `@.OFF.TAG` and the deactivated-tag marker, is recognized as a deactivated representation rather than as a malformed tag. Its compatibility details belong in the test corpus.

`@.OFF.TAG` has normalized name `@TAG` and retains `@.OFF.TAG` as `raw_name`.

- In **fast mode**, directly disabled tags are omitted. For `@.OFF.IF(...)`, the parser performs only enough structural scanning to locate the matching outer parenthesis safely; it does not parse, emit, or register conditions for the contents. Thus no nested item can enter the fast result. If a disabled `@IF` is unterminated, fast mode treats the remaining text as disabled content and emits no untrusted nested result.
- In **diagnostic mode**, directly disabled tags and `@IF` containers are returned with `enabled: false` and `explicitly_disabled: true`. The contents of a disabled `@IF` are parsed for author feedback. Descendants retain their own `explicitly_disabled` value but receive `enabled: false` because their enclosing container is disabled.

Diagnostic nodes may include `disabled_by` references when an enclosing disabled `@IF` caused `enabled: false`. This is provenance for feedback, not a semantic validation result.

## `@IF` Structural Syntax

`@IF` is identified by its required uppercase spelling. It is a syntactic container, not an ordinary parenthesized parameter.

The existing core behavior establishes the canonical form:

```text
@IF(condition, then-annotation, else-annotation)
```

The parser must find the two separators only at the `@IF` argument's top level. Commas inside quoted strings, JSON-like values, balanced parentheses, or nested `@IF` constructs do not split the three arguments. The parser retains all three raw argument ranges.

An explicit empty quoted false branch (`''` or `""`) is a valid three-arm `@IF` whose false annotation contains no action tags; it must not produce `if_missing_else`.

The direct condition is opaque:

- preserve its text and source range;
- do not call `REDCap::evaluateLogic`, `Form::replaceIfActionTag`, piping, or a logic parser;
- do not diagnose invalid field references, operators, quoting, or logical precedence; and
- only diagnose the `@IF` wrapper's own structural failures, such as missing delimiter or closing parenthesis.

An arm containing only whitespace is structurally absent: an all-whitespace
condition produces `if_missing_condition`, rather than becoming an opaque logic
expression. This is a wrapper-level check, not logic validation. The PHP and
browser mirrors must share the explicit ECMAScript whitespace and
line-terminator character set so that they cannot disagree about whether an
arm exists.

The `then` and `else` ranges are annotation fragments. They are recursively parsed for tags and nested `@IF` containers.

The parser defines an internal, non-public implementation switch:

```php
private const ACCEPT_IF_THEN_SHORTHAND = false;
```

With its default value, a two-arm `@IF(condition, then-annotation)` is diagnosed with `if_missing_else`. When explicitly enabled in a future core release, it is accepted as shorthand for an empty false branch and is represented with `else: []`. This switch is deliberately internal rather than a public parse option: compatibility is a REDCap behavior decision, not a caller-specific interpretation of the same annotation.

## Condition Definitions and Branch References

The parser does not generate a concatenated REDCap logic string for nested branches. Instead, each valid `@IF` occurrence creates one direct condition definition in the top-level `conditions` map. The condition text is preserved exactly and is never normalized, evaluated, or logic-validated.

Condition IDs are sequential positive integers in source order within one parse result. They are result-local references, not persistent identifiers to compare across parser modes or separate runs. A condition occurrence receives a distinct ID even when its raw text is identical to another occurrence. This preserves source locations, avoids collision/deduplication rules, and keeps results deterministic. Cross-`@IF` optimization of equivalent logic is intentionally out of scope; a later consumer may cache or intern exact expressions if it has a justified use case.

Each non-`@IF` tag has a `conditional` entry containing an ordered list of references:

```php
[
    ['id' => 1, 'negated' => false],
    ['id' => 2, 'negated' => true],
]
```

An empty list means the tag is unconditional. List order is the nesting order and is an implicit logical AND. A true `@IF` branch appends `['id' => n, 'negated' => false]`; its false branch appends `['id' => n, 'negated' => true]`. The `negated` flag corresponds to REDCap's `!` notation if a downstream consumer chooses to render or evaluate the condition. The parser itself does neither.

For example:

```text
@IF([a] = '1', @IF([b] = '2', @HIDDEN, @READONLY), @REQUIRED)
```

produces these condition definitions and tag references:

```php
'conditions' => [
    1 => ['raw' => "[a] = '1'", 'start' => 4,  'end' => 13],
    2 => ['raw' => "[b] = '2'", 'start' => 19, 'end' => 28],
],

// @HIDDEN
'conditional' => [
    ['id' => 1, 'negated' => false],
    ['id' => 2, 'negated' => false],
],

// @READONLY
'conditional' => [
    ['id' => 1, 'negated' => false],
    ['id' => 2, 'negated' => true],
],

// @REQUIRED
'conditional' => [
    ['id' => 1, 'negated' => true],
],
```

An `@IF` node's `conditional` is the inherited list that applies to the container itself. Its `condition_id` identifies its direct opaque test. Thus a nested `@IF` is visible as conditionally present before its own true/false branch reference is appended.

## Result Contract

The exact PHP array keys are part of the public contract once implementation begins. The following is the first proposed version.

### Shared tag-node fields

```php
[
    'type'            => 'tag',
    'name'            => '@HIDDEN',          // normalized (`_` becomes `-`)
    'raw_name'        => '@HIDDEN',          // source spelling (`@.OFF.HIDDEN` when disabled)
    'start'           => 24,                 // inclusive byte offset
    'end'             => 30,                 // exclusive byte offset
    'raw'             => '@HIDDEN',
    'enabled'         => true,
    'explicitly_disabled' => false,
    'parameter'       => null,               // or the parameter structure below
    'conditional'     => [                  // [] when unconditional
        ['id' => 1, 'negated' => false],
    ],
]
```

`parameter`, when present, includes a `kind` (`assignment`, `quoted`, `unquoted`, `json`, or `arguments` as applicable), its raw source range/text, and parsed value data only where parsing is unambiguous. JSON is assigned as the kind only after successful decoding; JSON-like text that fails to decode retains its ordinary quoted or unquoted string kind.

### Fast-mode result

```php
[
    'mode'       => 'fast',
    'conditions' => [ /* direct condition definitions keyed by source-order ID */ ],
    'tags'       => [ /* flattened enabled non-@IF tag nodes in source order */ ],
]
```

Fast mode omits text nodes, `@IF` containers, disabled tags, candidate nodes, detailed recovery state, line/column data, and detailed diagnostics. A valid enabled tag nested in a valid enabled `@IF` is included in `tags` with its effective `conditional` references. A malformed or disabled `@IF` does not yield trusted flattened children or condition definitions in fast mode.

### Diagnostic-mode result

```php
[
    'mode'        => 'diagnostic',
    'conditions'  => [ /* direct condition definitions keyed by source-order ID */ ],
    'nodes'       => [ /* root tag, @IF, and candidate nodes in source order */ ],
    'tags'        => [ /* flattened non-@IF valid tag nodes in source order */ ],
    'diagnostics' => [ /* ordered syntax findings */ ],
]
```

`nodes` contains every valid `@IF`, including disabled `@IF` containers, and, when useful for user feedback, malformed candidate nodes. With `include_text_segments`, it also contains text nodes that cover the remaining source. `tags` remains a convenient flattened view; it never includes `@IF` wrappers. Unlike fast mode, it can include disabled tag nodes, whose `enabled` field is false.

A diagnostic `@IF` node is proposed to have this shape:

```php
[
    'type'              => 'if',
    'name'              => '@IF',
    'raw_name'          => '@IF',
    'start'             => 0,
    'end'               => 53,
    'raw'               => "@IF([a] = '1', @HIDDEN, @READONLY)",
    'enabled'           => true,
    'explicitly_disabled' => false,
    'conditional'       => [],               // inherited context only
    'condition_id'      => 1,
    'then'              => [ /* recursive nodes */ ],
    'else'              => [ /* recursive nodes */ ],
]
```

The entry at `conditions[1]` holds the direct raw condition and its range. For a false branch, the child `conditional` contains `['id' => 1, 'negated' => true]`; neither the stored condition text nor any generated logic string is altered.

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

Byte offsets remain authoritative. A separate, opt-in diagnostic presentation helper may amend a completed result with Unicode-aware line, character-column, and byte-column data; this must not add work to fast mode.

The initial diagnostic-code set should include:

| Code | Meaning |
| --- | --- |
| `possible_action_tag` | `@` starts text that resembles a tag but cannot be confirmed. |
| `invalid_tag_name` | A tag candidate violates the accepted name form. |
| `unterminated_quoted_parameter` | A quoted parameter reaches its recovery boundary without a matching quote. |
| `unterminated_parenthesized_parameter` | A parenthesized argument does not close. |
| `unbalanced_parameter_delimiter` | An unexpected closing delimiter or impossible nesting state occurs. |
| `deprecated_underscore_action_tag_name` | An accepted uppercase name contains an underscore, which was normalized to a dash. |
| `deprecated_unquoted_parameter` | An unquoted assignment parameter was accepted for compatibility. |
| `deprecated_single_quoted_json_object_parameter` | A single-quoted JSON object wrapper was accepted for compatibility. |
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
- current ordered conditional-reference list;
- an `@IF` frame containing the direct condition range/ID, current arm (`condition`, `then`, or `else`), top-level separator count, enabled state, and diagnostic node being built; and
- parent node/sink references appropriate to the selected mode.

When an enabled `@IF` opens, push an `@IF` frame. While scanning its condition, only a comma at that frame's top level completes the condition. Once the condition is complete, register one source-order condition definition and derive the true-branch conditional-reference list. The second top-level comma switches to the false branch, which appends the same ID with negative polarity. The matching close parenthesis completes the frame and returns control to the parent annotation context.

Nested `@IF` frames use the current branch's references, so conditions are inherited without a later tree walk or reparsing. In fast mode, a disabled `@IF` uses a lightweight skip frame that finds its matching close parenthesis but never parses its body. In diagnostic mode, it uses a regular frame marked disabled so its body can be checked and all descendants can inherit `enabled: false`. In fast mode, enabled frames retain only the minimum metadata necessary to propagate references and emit leaf tags. In diagnostic mode, they also retain the container and branch nodes.

### Delimiter and quote handling

Every state that can contain structured text must observe:

- single- and double-quoted strings;
- backslash escapes inside quoted strings where the relevant syntax permits them;
- balanced `()`, `[]`, and `{}` delimiters as required for the active parameter/`@IF` context; and
- commas only when the active `@IF` frame considers them top-level separators.

The parser need not understand REDCap logic inside an `@IF` condition. It only balances enough structure to locate its argument boundaries safely. This is structural recognition, not logic validation.

### Output sinks

Use a mode-specific emission sink rather than maintaining two parsers:

- **Fast sink:** append valid enabled non-`@IF` nodes to `tags`; discard containers, disabled constructs, and detailed recovery records.
- **Diagnostic sink:** append root/branch nodes, retain `@IF` containers and candidates, append diagnostics, and optionally include text segments.

Shared scanning and node-construction helpers guarantee that a valid enabled leaf tag has the same name, range, parameter, and conditional structure in both modes. Condition IDs are result-local, so callers compare condition text/polarity rather than raw IDs when comparing separate parses.

## Performance Requirements and Existing Harness

- One forward scan in the normal case; no repeated parsing of nested `@IF` source ranges.
- O(n) time and O(n) output memory, with stack space bounded by `max_nesting_depth`.
- Fast preflight: return an empty tag list immediately when the input has no `@` byte.
- No broad/nested PCRE in the hot path. Small, anchored checks may be considered only if benchmarks demonstrate bounded behavior, but byte classification is preferred.
- Build strings from source offsets instead of per-character arrays where possible.
- Compute line/column locations only for diagnostics that will be returned.
- Keep caching outside the parser; callers may cache results using annotation text and parser-version/options as keys.

The EM's `benchmark.php` page implements the first interactive harness. It
compares fast and diagnostic pure parsing with the legacy parser and
`ActionTagHelper`, then exposes resolver phase timings and workload metrics for
a selected project/record context. This is already useful for controlled
project-level comparisons and for separating parser cost from data/evaluation
cost.

The next evidence stage is deliberately deferred until the structural behavior
has settled: collect a curated, de-identified real-world corpus and run it
through the harness. That corpus should include ordinary annotation text, many
independent tags, JSON parameters, quoted delimiters, nested `@IF` containers,
deep malformed inputs, and intentional differences from legacy consumers. The
acceptance criterion is predictable linear growth and documented compatibility,
not one absolute runtime number.

## Test Corpus and Acceptance Cases

The shared fixture suite must cover at least:

1. Native and External Module bare tags, uppercase name enforcement, hyphen/underscore names, and names with digits where currently used.
2. Tags with quoted, unquoted, JSON, and parenthesized parameters, including delimiters inside quotes and escaped quotes.
3. Exact start/end/whitespace tag-boundary behavior, including email-like strings that must not become tags.
4. Core deactivation forms: disabled individual tags omitted from fast mode but returned with `enabled: false` in diagnostic mode; a disabled `@IF` body skipped in fast mode but parsed as disabled content in diagnostic mode.
5. A canonical true/false `@IF`, an `@IF` containing multiple tags per branch, and `@IF` inside each branch of another `@IF`.
6. Exact source-order `conditions` entries and effective ordered `conditional` references for every nested true/false branch combination.
7. Diagnostic-tree retention of all valid `@IF` nodes, contrasted with their absence from fast-mode `tags`.
8. Invalid `@IF` wrappers: missing comma, missing arm, extra top-level comma, unclosed outer parenthesis, and quotes/parentheses within the opaque condition.
9. Recovery after malformed ordinary tags and malformed `@IF` constructs, with a following valid tag still located.
10. Deprecated parameter forms: unquoted values and successfully decoded single-quoted JSON objects. Include unquoted valid JSON arrays/objects plus quoted arrays and JSON-like values that must remain ordinary strings.
11. The default rejected two-arm `@IF`, an explicit empty quoted false branch, and the accepted shorthand behavior when the internal switch is enabled in a dedicated test configuration.
12. Limit and performance tests for deeply nested, oversized, and adversarial inputs.

Before runtime migration, fixtures must also record any intentional behavioral differences from legacy `Form`/`ActionTags` helpers. The parser's role is a stable structural standard, not an undocumented emulation of every historical regex quirk.

## Explicit Non-Goals

- Evaluating an `@IF` condition or deciding which branch is active.
- Validating REDCap logic syntax or field references inside a condition.
- Validating that a tag exists, is enabled by a project/module configuration, supports a parameter kind, or is legal for a field/context. The syntactic `@.OFF.` enabled state is the sole exception.
- Resolving piping, record values, project metadata, or External Module configuration.
- Replacing `Form::replaceIfActionTag()` or legacy helper behavior in the same change that introduces this parser.

Those responsibilities remain with core runtime code and the future semantic action-tag validator. The parser supplies the source-faithful structure those layers need.

## Current Editor Semantic Advisories

The parser remains structural-only, but the enhanced authoring editor now adds
a separate, advisory registration check after diagnostic parsing. The matching
PHP and browser `ActionTagSemanticAnalyzer` products inspect completed,
enabled entries in the parser's flattened `tags` result and compare their
normalized names with the project `action_tags` catalog. That catalog is built
from `Form::getActionTags()` and `ExternalModules::getActionTags($project_id)`,
so an `unknown_action_tag` warning means only that the name is not registered by
core or an enabled External Module for the current project.

The registration warning highlights the source name only, never its parameter,
and does not prevent editing or saving. Deactivated tags, including content in
a disabled `@IF`, receive no registration warning. An absent catalog preserves
structural-only output for stale browser/server callers, whereas a present
empty collection intentionally declares that no names are registered.

The same analyzer now consumes seven explicit `ActionTagCatalog` contracts.
`Form::getValueInQuotesActionTag()` extracts `@DEFAULT` only when it has an
equals sign and a nonempty single- or double-quoted value. `DataEntry` pipes
that value but deliberately skips the tag for File Upload/Signature fields, so
the editor warns for those exact cases while retaining whitespace-only values,
quoted JSON-looking text, and unconstrained other field types. The simple
runtime extractor does not support escaped delimiter quotes, which receive an
advisory warning. The quoted Piping contents remain the normal Piping
analyzer's responsibility.
`@PLACEHOLDER` uses the same extractor and quote constraint. `DataEntry` adds
the resulting HTML attribute only to Text Box and Notes fields, or to the
visible Auto Complete input for a Drop-down or SQL field. The editor retains a
Drop-down/SQL field when its validation context is absent, but warns when an
explicit validation proves that it is not `autocomplete`. Placeholder Piping is
performed only when DataEntry has a record context, which the Action Tag
contract intentionally does not infer.
`@SETVALUE` and legacy `@PREFILL` share that extractor and the same File
Upload/Signature exclusion in `DataEntry`. `Design/action_tag_explain.php`
intentionally hides `@PREFILL` as no longer used, so the editor gives its name
an advisory `deprecated_action_tag` warning that recommends `@SETVALUE` while
retaining all of the shared syntax and applicability diagnostics. If both tags
are present on a field, the existing runtime selects `@SETVALUE`'s value; that
collision precedence is preserved rather than inferred as a separate editor
restriction.
`@READONLY` is likewise an explicit no-parameter contract. Its runtime owner,
`Form::disableFieldViaActionTag()`, recognizes the exact whitespace-delimited
tag token rather than parsing a value. The editor therefore warns for
assignments and parenthesized arguments instead of endorsing a
whitespace-dependent legacy spelling such as `@READONLY = value`.
`Calculate::buildCalcTextEquation()` and `buildCalcDateEquation()` extract a
parenthesized, nonempty Logic expression from their respective tags only while
calculating a Text Box field; `buildCalcDateEquation()` additionally returns no
equation unless the target validation begins with `date` or `datetime`. The
Field Annotation editor supplies its current unsaved field type and validation
to the browser analyzer and fallback endpoint. An unsupported form produces an
advisory warning rather than a save blocker, because legacy metadata can still
contain a tag that does not become a calculation. No other built-in tag and no
External Module tag receives parameter or field-context inference until its
runtime behavior is independently cataloged.

This layer does not evaluate `@IF`, validate a condition, resolve piping or
record values, or derive any module configuration; those remain future
schema-backed semantic work.

## Implementation Transition

The EM transition is complete:

1. The former EM implementation is retained as `ActionTagParser_Old` only for
   comparison and compatibility investigation.
2. The standalone parser uses the canonical `ActionTagParser` name in the EM
   and in core.
3. The pure parser remains separate from EM metadata access, runtime `@IF`
   resolution, and Field Annotation semantic checking.
4. The current core branch additionally contains shared syntax primitives,
   logic, piping, and field-embedding parser products, a public logic catalog,
   browser mirrors, and Online Designer, Project Setup/Define My Events, and
   Survey Settings, Automated Survey Invitation and manual invitation
   composition, Survey Queue custom text and conditions, Form Display Logic,
   Record Status Dashboard, Data Export report builder, PDF Snapshot,
   Randomization, MyCap participant conditions and label, Project Setup's
   Data Entry Trigger URL, Project Dashboard body text, e-Consent custom
   labels, Alerts & Notifications, and Data Quality rule workspaces for logic,
   annotations, SQL highlighting, and piping-capable text.

Future work should preserve that separation, align browser/server behavior,
extend piping authoring deliberately beyond the currently integrated direct
data-entry-form design and Survey Settings surfaces, and move runtime consumers
only through explicit compatibility decisions.

The initial real-world compatibility scan found that legitimate unique event
names can begin with a digit (for example, `72_hours_arm_1`). The shared PHP
and browser piping parsers accept that structural form. The matching
`PipingSemanticAnalyzer` products then consume a completed parse and the
project authoring catalog to diagnose unknown field/smart-variable references,
unknown named events, fields not designated for an event, and checkbox target
or choice misuse. They do not add metadata cascades after a structural error,
and remain diagnostic-only. Built-in Piping smart variables receive structural
`supports_event_qualifier` and `supports_instance_qualifier` capabilities from
`PipingSmartVariableCatalog`; an explicitly unsupported qualifier produces a
warning because runtime replacement accepts but ignores that context. An absent
capability remains non-diagnostic for stale catalogs and future module-provided
variables. `PipingSmartVariableCatalog` now also carries only evidence-backed
parameter contracts: project instrument targets, survey-only targets, and
enumerated modifiers such as duration units. These drive browser completion
and PHP/browser semantic parity. An unknown instrument is a warning because
runtime replacement may fall back to the current form; a known non-survey used
where a survey is required is an error. Named project-dashboard targets are
transported as a read-only `dashboards` collection: `[dashboard-access-code]`
and `[dashboard-url]` take one unique dashboard name, while
`[dashboard-link]` also accepts one free-text label. An unknown dashboard in a
current catalog is an error because the replacement has no fallback; an absent
collection remains non-diagnostic for stale callers. `[report-access-code]`
uses the same read-only target pattern for existing unique report names. Its
runtime target must start with uppercase `R-`; a current report collection
diagnoses an unknown target as an error, while an absent collection remains
compatible. When a known instrument is paired with a known named event, an
absent event-form designation is also a warning: the completion list leaves
the instrument available but visually muted. Link text and other unrestricted
or unreviewed parameters remain runtime behavior. Where replacement code has
an exact upper parameter count, the same catalog exposes `max_parameters`; an
excess is a warning-only diagnostic. For example, `[survey-queue-url]` takes none and
`[survey-queue-link]` takes one optional free-text label. Their replacement
accepts a record or a numeric survey participant ID, which it first resolves
to a record before constructing the queue link. They therefore use the
separate `requires_record_or_survey_participant_context` capability—not the
recordless public-survey route. The bulk Survey Invitation composer is the
first cataloged participant source; any future source must explicitly declare
`has_survey_participant_context: true` only after its runtime call is
verified. Catalog-backed
semantic analysis also excludes the
permissive runtime's final inline numeric or named instance from supported
author syntax: qualifier-reviewed smart variables and project fields receive a
blocking diagnostic for `[survey-url:followup:last-instance]` and must use
`[survey-url:followup][last-instance]`. The structural parser remains opaque
where numeric parameters are legitimate, such as `[data-table:435]`.
Randomization is a separately documented exception: `[rand-number:2]` is its
sequence-reference parameter, rather than an inline instance qualifier, and
`[rand-time:2:value]`/`[rand-utc-time:2:value]` additionally request a raw
timestamp. That explicit contract is cataloged rather than inferred from the
generic preprocessor's broader numeric shuffling.
`[instrument-name]` and `[instrument-label]` read the current form (or the
form identified by a supplied survey participant) and accept neither
parameters nor event/instance qualifiers. `[survey-title]` has the same
fallback but may instead use one known survey instrument parameter, as in
`[survey-title:followup]`; it resolves blank for a non-survey form. In a known
source with no current form, the editor therefore keeps the title variable
available to complete an explicit survey instrument but warns for the omitted,
unknown, or non-survey target. These three variables ignore event and instance
qualifiers, which completion mutes and semantic analysis warns about.
The survey-progress family—`[survey-date-completed]`,
`[survey-time-completed]`, `[survey-date-started]`,
`[survey-time-started]`, `[survey-duration]`, and
`[survey-duration-completed]`—instead queries a survey response using the
current record, event, target survey form, and optional instance qualifier.
Each supports event and instance qualifiers and may select its survey through
the first parameter when no current form is supplied. The catalog therefore
requires record and event contexts plus
`requires_form_or_instrument_parameter`: a form-less source keeps these
variables available to complete a known survey parameter but warns for an
omitted, unknown, or non-survey target. This remains source-context advice;
whether the selected participant has actually started or completed that survey
is normal runtime output, not an authoring diagnostic.
`[form-url]` and `[form-link]` build a Data Entry URL from the current record,
event, form, and optional instance qualifier. Both accept event and instance
qualifiers and their first parameter may select any known project form when
the source has no current form; `[form-link]` accepts a second free-text link
label. They therefore use the same record, event, and
`requires_form_or_instrument_parameter` contract, but completion calls for an
`instrument` rather than a `survey instrument`. An unknown first parameter of
`[form-link]` is deliberately not diagnosed when a current form exists: the
runtime treats it as the custom link label fallback. Without a current form it
cannot select a target, so the source-context warning still applies.
`[survey-url]` and `[survey-link]` derive their target link from the current
event, survey form, and optional instance qualifier. Both can use either a
record or the recordless public first-survey route, and otherwise require the
current survey form or a known explicit survey parameter. A form-less
record-backed source therefore prompts for a survey target; Twilio's public
route leaves a bare URL/link active and limits an explicit target to its
configured first form. A source that explicitly supplies a survey participant
has a separate bare-target route: `[survey-url]`, `[survey-link]`, and
`[survey-access-code]` use that participant's current survey only when their
first instrument parameter and event/instance qualifiers are omitted. The
catalog represents that narrow route with
`supports_survey_participant_context`, so recordless bulk Survey Invitations
keep those bare variables active but still warn for an explicit target such as
`[survey-url:followup]`. `[survey-link:custom text]` is a
current-form label shortcut, not a public- or participant-target selection,
so it is warned when no current form can make the shorthand meaningful.
`[survey-access-code]` derives its value through the same prepared survey-link
route. `[survey-return-code]` instead calls
`Survey::getSurveyReturnCode()` and immediately returns empty text without a
record. Both codes consume the current event (or a supported event qualifier)
and need the current survey form or a known explicit survey parameter in a
form-less record-backed source. In Twilio's recordless public route,
completion leaves a bare access code active and limits an explicit survey to
the configured public first form; return codes remain muted and warned because
they cannot use that route. Response-specific code generation or availability
remains a runtime result.
The follow-up Survey Invitation subject and rich-text content share a single
source policy because their Data Entry submit path pipes both with the current
record, event, selected survey form, and authenticated-request user fallback.
They can therefore receive normal field and context-aware Smart Variable
completion. Their Piping call runs on `Surveys/invite_participant_popup.php`,
so the fixed source policy also warns/mutes `[is-survey]` and `[is-form]` as
zero rather than inheriting the Data Entry page's state. The bulk invitation
composer uses the shared `survey_invitation_bulk_email` policy, but declares
its record, event, and form contexts as `recipient_dependent_contexts` rather
than as fixed booleans. `email_participants.php` pipes each selected
participant independently: a record-backed participant receives record,
event, and survey-form arguments, while a recordless initial-survey
participant receives only its participant ID. The Participant List exposes
that provenance to the opener, which supplies a live three-state
`piping_context_availability` value—`guaranteed`, `partial`, or
`unavailable`—for those three contexts. A mixed selection keeps field and
Smart Variable completion available but labels record-dependent entries and
warns at completed references that only some selected recipients can resolve
them. A fully recordless selection suppresses project-field completion and
uses the established unavailable-context warnings, except that the bare
participant-backed `[survey-url]`, `[survey-link]`, and
`[survey-access-code]` remain available; a fully record-backed selection has
normal completion and no advisory. The same narrow state is
passed to the browser analyzer and the server diagnostic fallback. The bulk
policy separately declares its guaranteed participant and authenticated-user
contexts and its `Surveys/email_participants.php` evaluation route.
`[is-survey]` and `[is-form]` instead inspect the global `PAGE` constant, not
Piping arguments. They accept no parameters and ignore event/instance
qualifiers. `[is-survey]` is `1` only on `surveys/index.php`; `[is-form]` is
`1` only on `DataEntry/index.php` with the required `id` and `page` request
values. `truthy_runtime_page` records the proven page condition without
misrepresenting these valid variables as form-context consumers. A source
policy declares `piping_runtime_page` only where its route is fixed: Survey
Queue custom text is rendered on `surveys/index.php`, so `[is-survey]` remains
active there while `[is-form]` is muted and warned as a fixed `0`. Sources
whose route can vary retain normal runtime compatibility.
`[record-name]`, `[record-dag-id]`, `[record-dag-name]`, and
`[record-dag-label]` read the current record only; the DAG variables then look
up that record's assigned Data Access Group. They accept no parameters and
ignore event/instance qualifiers. Their explicit record-context requirement
therefore mutes and warns them in proven recordless sources, while a current
record without a Data Access Group remains a normal blank runtime result.
`[arm-number]`, `[arm-label]`, `[event-id]`, `[event-number]`,
`[event-name]`, and `[event-label]` similarly read only the current event. The
Piping preprocessor restores the incoming event ID before it replaces each
tag, so a structurally valid prepended event or an appended instance does not
alter these values. They accept no parameters; completion mutes ignored
qualifiers and semantic analysis warns about them, while known eventless
sources warn and mute the variables themselves.
The bare relative-event values follow the same no-parameter, ignored-qualifier
contract. `[previous-event-name]`, `[previous-event-label]`,
`[next-event-name]`, and `[next-event-label]` need a current event and resolve
blank when there is no adjacent event. `[first-event-name]`,
`[first-event-label]`, `[last-event-name]`, and `[last-event-label]` instead
use Piping's intentional first-event fallback when no current event is passed,
so they remain available in known eventless sources. These names also retain
their separate structural role as dynamic event selectors before another
Piping reference; the bare-variable catalog contract does not restrict that
following reference's qualifier capability.
The standalone repeat-instance values—`[previous-instance]`,
`[current-instance]`, `[next-instance]`, `[first-instance]`,
`[last-instance]`, and `[new-instance]`—are separately cataloged as bare
Smart Variables. They require record and event contexts plus either the
current form or a repeating current event. The last alternative is represented
by `requires_form_or_repeating_event_context`, because the runtime can obtain
repeat-event instances without a form argument. They accept no colon
parameters and do not consume outer event or instance qualifiers; their
distinct use after a field reference remains the structural relative-instance
mechanism for that field. The controller transports the known repeating state
of the project first event to form-less sources. Therefore Survey Queue custom
text mutes and warns the family when that first event is nonrepeating, but
keeps it available when the first event itself repeats. This is a context
availability contract, not a value guarantee: previous/next still resolve
blank at a missing neighboring instance, and new-instance remains blank in a
nonrepeating form context.
`[user-name]`, `[user-fullname]`, `[user-email]`, the three `[user-dag-*]`
values, and the three `[user-role-*]` values derive solely from the supplied
user (or Piping's authenticated-request `USERID` fallback). They accept no
parameters and ignore event/instance qualifiers. They resolve blank if neither
user route is available, so the catalog treats them as advisory user-context
requirements: known userless sources mute and warn them without blocking a
valid expression in a source that can provide `USERID` at runtime.
`[project-title]`, `[project-id]`, `[project-status]`, `[project-purpose]`,
`[project-irb-number]`, `[redcap-base-url]`, `[redcap-version]`,
`[redcap-version-url]`, and `[survey-base-url]` come from the active project
or application configuration. They accept no parameters and ignore
event/instance qualifiers, but remain available in every project-scoped
authoring source without record, event, form, or user context.
The same catalog's explicit `requires_record_context`,
`requires_event_context`, `requires_form_context`,
`requires_form_or_instrument_parameter`, `requires_user_context`, and
`requires_record_or_public_survey_context` capabilities are limited to runtime
cases that read or require the corresponding value directly. In a named source
that declares the corresponding `has_*_context: false`, those variables
produce a warning and remain visibly muted in completion. The Twilio
manual-invitation path passes its first project event and a public-survey route
but no record, form, or user; it transports the project's `firstForm` name so
the authoring tools offer and accept only that public URL/link target. Project
Dashboard rendering passes none of these contexts, so survey URLs and links
are advisory warnings there. This avoids a guess-driven Smart Variable
allow-list.
Shared PHP/JS semantic fixtures cover this boundary.

`PipingFieldParameterCatalog` separately captures evidence-backed colon
modifiers for project fields (for example `:checked`, `:year`, and `:link`).
The controller transports each field's applicable `piping_parameters` for
completion and the complete `piping_field_parameters` universe for semantic
analysis. The PHP and browser analyzers warn only when a modifier is known to
that universe but absent from the referenced field's contract; an unknown
modifier remains runtime-compatible. The field-local contract keeps the
completion UI, server fallback, and browser analyzer from reimplementing
field-type, validation, and relevant field-metadata rules independently.
Definitions may also share an `exclusive_group` where runtime gives only one
modifier meaningful effect (such as checked versus unchecked checkbox choices,
inline versus linked files, or date/time component selection). The analyzers
warn and completion suppresses the conflicting later choice; this remains
advisory because Piping replacement itself is unchanged.

`PipingSourcePolicyCatalog` captures the narrower question of whether a named
authoring source has record, event, form, user, public-survey,
survey-participant, or proven repeating-event contexts at all. The
controller sends those source policies in the same catalog used by the browser
and server fallback. The Twilio policy additionally carries its project first
form as `public_survey_form`. A declared recordless source suppresses
project-field completion and produces an advisory finding for `[field_name]`;
it also warns only for Smart Variables that explicitly require a context the
source does not provide. An absent or future source policy remains compatible.
This makes completion and metadata-aware diagnostics agree without treating an
authoring-source name as a parser concern.
The default Survey Queue custom-text renderer supplies its record and the
project first event but no current form, so form-dependent Smart Variables are
warning-only and muted while field, record, and event references remain
available. Its transported `has_repeating_event_context` value is the actual
repeating state of that first event, allowing only the cataloged form-or-repeat
alternative when it is true. Its language-specific queue translations use
their own rendering contexts and are deliberately not claimed by this
default-source policy.
When source replacement depends on a selected delivery mode, the same policy
can declare `piping_delivery_types`. The Twilio manual-invitation editor passes
the current `delivery_type` as `pipingDeliveryType`; only `SMS_INVITE_WEB`
calls `Piping::pipeSpecialTags()`. Any other selected method receives a
warning for each syntactically valid reference, mutes initial smart-variable
completion, and suppresses qualifier and parameter suggestions. No selection
preserves compatibility for older callers and static review contexts.

Manual Piping completion also consumes the catalog's named events in
longitudinal projects. Selecting an event name produces only `[event_name]`.
When the user begins the following bracket, completion recognizes the preceding
event, ranks fields from its designated forms first, and keeps other project
fields as visually muted choices. The Piping completion popup is scoped to a
450px width (ACE's default is 300px) so field labels remain readable. Classic
projects have no event entries to suggest. After a completed smart variable,
the same catalog supplies named instance qualifiers: supported choices are
active and explicitly unsupported choices remain visible but muted. For a
dashboard smart variable's first parameter, the same popup instead completes
the project's existing unique dashboard names and displays their titles.
Catalog construction deliberately reads only existing names rather than
invoking the dashboard helper that may backfill them. `[report-access-code]`
similarly completes existing `R-…` report names and their titles without
invoking the report helper that backfills missing names.

The same `reports` collection is used by the cataloged
`supports_report_filter` capability of aggregate Smart Functions, Smart
Charts, and Smart Tables. `Piping::parseSmartParams()` removes whitespace,
uses the first uppercase `R-…` token after the field-list argument as a report
filter, and returns before inspecting later filtering tokens. Semantic analysis
therefore validates and completes the first report token, warns that later
distinct report tokens are ignored, and likewise warns when the report makes a
recognized current-context, named-DAG, or named-event filter ineffective. It
completes `record-name`, `event-name`, and `user-dag-name` filters with their
source-context availability, plus project DAG and event names (preferring a
DAG when a name collides). Unknown free-form tokens—including lower-case
`r-…` and report-like field-list entries—remain opaque runtime behavior.

For `[stats-table]`, the catalog also describes the runtime-supported output
columns and each project field carries a `stats_table_supported_columns` list
derived from `DataExport::getDescriptiveStats()`. Both analyzers warn, and
completion mutes only when appropriate, for a requested output that REDCap
will leave blank. This preserves the existing numeric-column warning for
`min`/`max`/`mean`/`median`/`stdev`/`sum`, while covering field-specific cases
such as `unique` on the record ID field. The record ID supports only `count`
and `missing`; categorical fields also support `unique`; numeric fields support
the complete output set; and descriptive fields support none. A missing field
capability remains non-diagnostic for a stale catalog.

`[data-table]` now has one optional, canonical positive-integer `project_id`
parameter. Omission (or an empty parameter) retains its runtime meaning of the
current project. A malformed explicit value is a browser/server semantic error.
Target lifecycle checks are deliberately server-backed: the browser sends only
the expression's requested IDs to a narrow AJAX endpoint, which returns no
titles or project catalog. A nonexistent, deleted, or Completed target is an
error; Analysis/Cleanup is a warning; Development and Production targets,
including Production Draft Mode, remain valid. The server fallback uses the
same requested-target lookup.

Calendar Feed variables have the runtime-backed parameter contracts
`[calendar-url]` (no parameters) and `[calendar-link:link_text]` (one optional
free-text label). Extra parameters are warning-only because runtime ignores
them. The existing record-context diagnostic remains independent. Their
catalog entries declare a `calendar_feed` system-capability requirement; the
editor receives only that named setting and its enabled/disabled state. When disabled, both
variables remain recognized, appear muted in completion, and receive an
advisory that runtime resolves them to empty text rather than an unknown-name
error. Missing capability state remains compatible with stale catalogs.

Email Verification/Unsubscribe variables have the same catalog treatment.
`[email-verified]`, `[email-verify-url]`, `[email-verify-link]`,
`[email-unsubscribed]`, `[email-unsubscribe-url]`, and
`[email-unsubscribe-link]` require a record and the
`email_verify_unsubscribe` capability. The capability's enabled state is the
runtime conjunction of the global Email Verification/Unsubscribe setting and
active REDCap+ features. The URL and status variables accept no parameters;
the link variables accept one optional free-text label. When unavailable, the
variables remain recognized, muted in completion, and warning-only; missing
capability state remains stale-catalog compatible.

The REDCap SHARE family—`[redcap-share-url]`, `[redcap-share-link]`,
`[redcap-share-ehr-list-url]`, `[redcap-share-ehr-list-link]`,
`[redcap-share-ehr]`, and `[redcap-share-ehr-id]`—has the runtime-backed
`redcap_share` capability and a record-context requirement. Its enabled state
is the current project's `RedcapShareFeatureGate::isAvailableForProject()`
result, covering the actual system and project enablement checks; its catalog
`availability_scope` is therefore `project`. This
deliberately does not copy the legacy reference list's additional REDCap+
visibility guard, because the replacement utility does not use it. All six
variables accept no parameters. When the gate is unavailable, they remain
recognized, muted in completion, and warning-only; missing state remains
stale-catalog compatible.

The Rewards family—`[reward-amount]`, `[reward-product-id]`,
`[reward-product-name]`, `[reward-status]`, `[reward-redcap-order-id]`,
`[reward-provider-order-id]`, the six `[reward-redemption-*]` variables, and
the `[reward-link]`/`[reward-url]` aliases—is a deliberately different case.
`Piping::getSpecialTagsInfo()` hides its legacy help group behind Rewards
feature, project, and REDCap+ presentation conditions, but
`Piping::pipeSpecialTags()` still recognizes and converts the family without
checking those conditions. The authoring catalog therefore injects the family
when that reference group is absent, without adding a system-capability
warning or muting completion. Normal Piping requires a record, honors an event
qualifier to choose the record arm, ignores an instance suffix, accepts at
most one explicit `R-<positive integer>` option parameter, and otherwise
resolves empty text. An explicit malformed option ID and extra parameters are
warning-only. No missing-option warning is emitted: Rewards-specific renderers
can inject an option ID or directly replace redemption tokens before normal
Piping runs, so absence is source-dependent rather than a general syntax
error.

The Access Control Group placeholders—`[user-acg-name]`,
`[user-acg-noncompliant-rights]`, and `[acg-noncompliance-table]`—are likewise
injected when their legacy help entries are hidden. All require a user context:
their Piping cases throw without a user after the normal USERID fallback is
unavailable, so sources without user context receive an advisory and muted
completion. They accept no parameters. The global feature state is modeled
only for `[user-acg-noncompliant-rights]`: its external call is intercepted by
`AccessControlGroup::__callStatic()` and resolves empty when the feature is
disabled. `[user-acg-name]` still reads the user's stored group, while
`[acg-noncompliance-table]` computes its own report; neither has the same
runtime gate and neither receives a fabricated availability warning.

`[dashboard-access-code]` is also injected if public dashboards are globally
disabled. That setting hides only its legacy help entry; its
`pipeSpecialTags()` case still resolves a named dashboard in the current
project and retrieves its access code. It therefore remains active without a
system-capability warning or muted completion, using the same one-dashboard
parameter contract, current-project target validation, and completion list as
`[dashboard-url]`.

The MyCap project code has no record requirement. The three participant
variables—`[mycap-participant-code]`, `[mycap-participant-url]`, and
`[mycap-participant-link:link_text]`—do: the participant-code helper rejects a
missing record, while the URL/link cases guard their complete replacement. The
participant variables use the current runtime event to select the relevant arm,
but ignore both a preceding event qualifier and an instance suffix. The link
accepts one optional free-text label; the other MyCap variables take no
parameters. No MyCap-enabled availability capability is fabricated because
these Piping cases do not test that setting.

The Randomization variables require a record because their helper looks up an
allocation by record. They ignore event qualifiers and support only a numeric
bracketed instance suffix as an alternative sequence reference; named instance
suffixes are muted and warned. `[rand-number]` accepts one optional positive
integer `:n`. `[rand-time]` and `[rand-utc-time]` accept up to a positive
integer reference and/or `value` for the raw timestamp (including the
documented `:n:value` form). The analyzer cannot safely infer how many
randomizations a project has, so it validates only the positive-integer shape.

The same scan established the calculation compatibility policy. Historic
client-side JavaScript/jQuery stored in calculation fields is now illegal for
new expressions and must remain a syntax error; it is not a REDCap Logic
dialect to support. The intentional multi-expression `LogicAnalyzer` demo is
excluded from compatibility requirements, as are deliberately malformed
stress inputs. REDCap calculation modulus must use `mod()`; `%` remains
rejected by the shared logic parser fixtures.

The first metadata-aware layer is now implemented for Logic. The pure PHP and
browser `LogicSemanticAnalyzer` products consume a completed structural parse
and the project authoring catalog, then add diagnostics for unknown
field/smart-variable references, unknown events, fields unavailable at an
event, checkbox misuse or unknown choice codes, unknown functions, invalid
function arity, and directly inferable function-argument type mismatches. The
catalog carries field type/validation/value kinds, checkbox codes, event-form
designation, function parameter and result types, and the complete base
smart-variable set. Arithmetic operators require directly known numeric
operands, while logical operators require directly known Boolean operands;
unknown or mixed values are deliberately left unflagged. Semantic analysis
does not run after a structural error, so recovering malformed text does not
create misleading metadata cascades. It is
diagnostic/editor-only: it does not evaluate expressions or change runtime
validation.

Logic smart-variable entries now also carry a result type and allowed Logic
source kinds. The current safe baseline explicitly uses `*`, preserving
existing availability until source-specific runtime behavior is reviewed. The
analyzer nevertheless enforces a restricted entry when one is declared,
leaving a data-driven path for a future source-specific rule without inventing
one today.

Calculation authoring now warns about direct self-reference when the editor
supplies its target field. This applies to ordinary calculation fields and to
the Logic expression inside `@CALCTEXT(...)` or `@CALCDATE(...)`. It warns only
for an unqualified reference to that same field; an explicit other-event or
repeat-instance reference is permitted. The target can be a newly authored
field that is not yet in project metadata, so it does not also receive an
unknown-field error. Circular dependency analysis across multiple fields is a
separate graph check.

That later calculation graph check must run server-side only during a safe,
non-interactive design action (for example, after a field save or a metadata
import has assembled the proposed project definition). It must emit warnings
and never block or roll back that safe action. It should report the complete
cycle path for a calculated field in a given event/repeat context: ordinary
calc, CALCTEXT, and CALCDATE fields; direct and indirect field references; and
potential dependencies introduced through aggregate smart variables. A cycle
that crosses an event, repeat instance, record, or aggregate scope must be
identified as potential rather than presented as a guaranteed runtime loop.

The present `Calculate::getCalcFieldsByTriggerField()` routine is trigger
discovery based on text matching, not a dependency graph, so it must not be
reused as cycle proof. The pure server-side
`CalculationDependencyGraphBuilder` now supplies the necessary foundation:
normalized calc/CALCTEXT/CALCDATE source extraction, parsed reference edges,
event-context resolution, repeat/dynamic-context preservation as potential
edges, and machine-readable aggregate-smart-variable dependency semantics.
Its synthetic fixtures cover direct/indirect, event, repeat, aggregate, and
invalid-expression cases.

**Implemented Online Designer pre-close preview:** before **Update & Close
Editor** closes the Calculation Editor, it sends a read-only AJAX request that
combines the active development/draft metadata with the pending calc, CALCTEXT,
or CALCDATE source and target-field identity, including a newly added or
renamed field. When the proposed field participates in a cycle, an rcDialog
names the offending fields and shows the full field/event path, with potential
context-dependent paths labeled. The user may return to the editor or save
anyway; the save acknowledgement prevents the immediate post-save dialog from
duplicating the warning.

The post-save graph check remains the fallback for edits made outside that
preview flow. It uses the resulting development/draft metadata, displays only
cycle witnesses containing the field just saved, caps the display at ten paths,
and never blocks or rolls back the save. During the first Data Dictionary
review, the server compares the active development/draft graph with the
validated, parsed proposed metadata. It displays only introduced witnesses as
part of the existing warnings immediately above the box containing **Commit
Changes**, not as a post-commit dialog. Instrument ZIP and Copy Instrument
imports compare the pre-import graph with a freshly reloaded post-commit graph
and use the same capped full-path warning body. Every warning is advisory; a
reporting failure is logged without changing the completed import result.

The workspace's title-bar fullscreen control is likewise framework-independent:
it opts into `rcDialog`'s `fullscreenToggle` configuration, whose native click
and F2 handlers use bundled Font Awesome expand/compress icons. Entering
fullscreen saves the dialog's exact inline geometry, pins it at a 10-pixel
viewport inset, and disables dragging and resizing; collapsing restores the
saved position, dimensions, and resize controls. The Field Annotation source
textarea is read-only and opens only on click, with closure focus explicitly
returned to the field-name control so it cannot reopen itself or accept direct
text while an editor is active. A consumer can enter or collapse the same
dynamic mode programmatically after `dialog:shown` with
`ctx.setFullscreen(boolean)` and read it with `ctx.isFullscreen()`, even when
the title-bar control is not shown; static `size: "fullscreen"` dialogs are
intentionally not collapsible through that API. The compact title-bar glyph is
optically aligned with the close control by rcDialog itself, rather than by
workspace-specific CSS.

Every field-level workspace title includes the current variable name as a
code-styled ` - [field_name]` token with muted gray brackets. The common
`field.*` presentation rule leaves the base title in place only until a new
field has a name; its callers pass the current `targetField`, including Field
Label, Field Note, and Branching Logic, so the identifier remains visible in
both ordinary and fullscreen editing.

SQL keeps its deliberately limited authoring contract: ACE highlights the
supported SQL surface and supplies manual scoped completion, but does not
claim SQL parsing or validation. Its Help tab lazily retrieves the existing
project-scoped `Design/sql_field_explanation.php` JSON payload when opened and
renders that established guidance below the editor-specific notes. This keeps
the legacy SQL documentation as the single source of truth and avoids another
dialog.

Database-backed regression coverage creates temporary Development and Draft
Mode projects, then exercises that same pre-commit review helper against real
project metadata. It verifies that a pre-existing active-scope cycle is not
shown and that a newly proposed cycle is shown; the Draft Mode case proves the
comparison reads `metadata_temp` rather than production metadata.

Each authoring workspace invocation identifies its concrete source with a
stable logical `ref`. It is intentionally UI context, not parser input: the
pure parsers stay context-free. An exact-ref source-policy registry owns
static editor behavior—syntax, presentation, single- versus multi-line
handling, HTML mode, and field-embedding permission—while a call site supplies
only dynamic details such as save callbacks, focus targets, and the current
host form. This provides a deliberate home for future container-specific
completion or diagnostic policies without deriving meaning from a DOM selector
or changing parser contracts. The restricted `filter_tags` HTML mode used by
Custom Record Label, Custom Event Label, and Custom Repeating Instrument Label
also performs a one-line-safe `<br>` round trip: supported existing `<br>`
spellings display as newlines and can be saved as canonical `<br>` tags or
spaces, while Cancel preserves the original stored text.

The workspace's metadata catalog is cacheable only between unchanged project
definitions. Online Designer field additions and edits now reload the affected
form from the server; that refresh also replaces the cached catalog, so field
completion and semantic diagnostics immediately reflect added, renamed, or
deleted fields.

Some exact piping policies also deliberately suppress record-field completion.
The real-time Twilio SMS composer and Project Dashboard body do not have a
per-record replacement context, so they expose smart variables while leaving
dashboard-only charts, tables, and functions to their existing runtime and
wizard. This is an authoring-surface policy, not a change to the context-free
piping parser. Generic External Modules and Vue hooks remain outside the
registry until a caller supplies a concrete source and runtime grammar.

Field embedding follows the same parser boundary. Its pure PHP and browser
parsers recognize only REDCap's current curly-brace grammar (`{field_name}`
and `{field_name:icons}`), without consulting project metadata or changing
runtime replacement. In HTML-capable source editing, it is recognized only in
ordinary text nodes, never in markup, attributes, comments, or raw script/style
content. The accompanying PHP/browser semantic layer receives the host form,
field, and metadata attribute separately, and uses draft-aware catalog data
to flag unknown fields, record IDs, other-form and other-page targets,
self/nested embeddings, and a second use of the same embedded field on the
rendered page. Completion applies the same availability decision and suppresses
the record ID and already-used fields. The server fallback applies those checks
only to the same HTML text-node ranges as the browser path.
