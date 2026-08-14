# Manual: Adding or Changing an Authoring-Language Feature

This is the required maintenance and PR checklist for author-facing REDCap
language features. Keep this document current whenever an implementation seam
or required verification step changes. A feature is not complete merely
because it appears in completion, help, or diagnostics: runtime behavior,
documentation, authoring support, and test coverage must agree.

It is the Manual for integrating new Smart Variables, Piping project-field
modifiers, Special Functions, and Action Tags into their runtime registries,
authoring catalogs, help, completion, diagnostics, and tests. Extend it before
opening a PR whenever a new catalog or integration seam is introduced.

When a feature consumes project metadata through the shared authoring catalog,
also identify its invalidation boundary. Online Designer metadata changes
reload the affected form and refetch that catalog; do not add a second client
side field cache without an equally complete add/rename/delete refresh plan.

When a legacy control is shared between direct editing and an authoring
workspace, update its readonly state whenever the user changes field type in
the Edit Field dialog. `element_enum` is direct-editable for choice-owning
types; only Calculated Field and SQL use it as a readonly authoring-workspace
launcher. Cover sequential Radio → Calculated Field → SQL → Drop-down → Checkbox
transitions and assert both `readonly` and `aria-readonly` after every change.
Immediately dispatch the rendered focus/click handlers at every transition
state, so readonly changes and launch behavior cannot drift apart.
Do not treat the choice list as a raw authoring workspace until that separate
feature is intentionally designed.

Keep focus and click behind one dynamic launcher gate. Test that choice-owning
types take no workspace action, while Calculated Field and SQL open their
respective workspace with the current target-field identity and safe focus
return. When both events can fire for one gesture, rely on and test the shared
workspace opener marker: it must be set before asynchronous loading so a second
request resolves without creating another dialog. When that request belongs to
the already visible workspace, it must focus the existing ACE editor without
moving the dialog. Browser coverage must execute the rendered control's actual
focus-then-click handlers for both Calculated Field and SQL selections, and
assert one dialog creation, one dependency load, and refocus of the existing
editor for each. It must also execute those handlers for Radio, Drop-down, and
Checkbox selections, asserting no workspace request or opener marker and no
cancellation of native direct editing. Simulate a dependency-load failure for a
workspace-owning type, then verify the next rendered handler can retry because
the opener marker was cleared. Also hold dependency loading pending across a
focus-then-click pair and verify that it produces only one eventual workspace.
Apply that pending-load check to every readonly focus/click launcher, including
Quick-modify fields' custom Action Tags source, Data Quality Rule Logic, Data
Entry Trigger URL, End Survey Redirect URL, Auto-Continue condition, Custom
Record Label, Custom Event Label, Custom Repeating Instrument Label, Survey
Settings' Confirmation Email Subject, Bulk Survey Invitation Email Subject, and
Follow-up Survey Invitation Email Subject, and Automated Survey Invitation
Email Subject and condition logic, and Survey Queue condition logic; do not
apply it to a deliberately click-only surface such as Field Annotation. Form
Display Logic conditions need an additional repeater check: its dynamic rows
clone the source textarea and receive a new ID, so dispatch the inherited
handler and verify focus returns to that row's form/event selector. Retain each
source's existing click cancellation, focus-return, mode-selection, conditional
availability, and validation behavior while testing the shared guard. When a
source passes live context availability, test that the workspace retains the
provider function until current recipient state is needed. For generated HTML,
execute the decoded rendered handler rather than a hand-written equivalent.
Preserve nonblank condition activation, trimming, and the established validation
helper when testing a condition's save callback.
Apply the same delayed-launch and save-callback coverage to Record Status
Dashboard filter logic, which trims and validates without activating a separate
condition option.
Data Export advanced logic needs the same pending focus/click guard but must
delegate post-save checks to `check_advanced_logic()`; do not reproduce the
report builder's validation in the workspace callback.
PDF Snapshot trigger logic needs the guarded launch plus its existing nonblank
trigger activation, trimming, and validation callback.
Randomization real-time logic must preserve its Save-button enablement before
both focus and click workspace requests, including when the second request is
suppressed while dependencies are pending.
MyCap's participant-allow condition must retain its dialog-close focus return
and existing `checkLogicErrors()`/`validate_logic()` save callback while its
focus/click pair shares one pending workspace. Alerts condition logic has the
same validation-callback requirement; Alerts email subject and SendGrid
template-data values must retain their distinct focus-return selectors while
each pair produces only one eventual workspace.
e-Consent Custom Label must likewise keep its setup-dialog close-control
focus return while its rendered focus/click pair shares one pending workspace.
For rich-text source actions, execute the rendered button handler and assert
that it calls `REDCap.openTinyMCEAuthoringWorkspace()` with the intended
textarea and policy. Preserve Project Dashboard's Save return, Survey Queue's
dialog-close return and `fitDialog()` save callback, and the source policies
for Automated Survey Invitation email content and Alerts message.
Parameterize the same rendered-button check for Survey Settings' Offline
Instructions, Survey Instructions, acknowledgement, stop-action
acknowledgement, and confirmation-email body. Each must remain a cancellable
TinyMCE source action with exactly its catalog policy; do not fabricate a
focus-return or callback where its runtime handler has none.
Test the shared TinyMCE bridge itself with and without an active TinyMCE
editor. With one active, it must use a detached source buffer, write the saved
source back through TinyMCE, trigger the original field's input path, call the
source callback with the original field, and remove the buffer on close. With
no editor, it must retain the workspace-owned textarea path; a missing source
must return the established rejected promise without launching a workspace.
Exercise Field Label through that bridge for both ordinary labels and section
headers: ordinary labels target `element_label`, while a section header targets
its persisted `sq_id` host and `element_preceding_header`; both retain current
form field embedding and the keyup callback used for variable-name generation.
Test Matrix Section Header separately: its source belongs to the surrounding
form and the first persisted matrix field's `element_preceding_header`.
For Field Note, execute the rendered Enter, F2, double-click, and explicit
source-action handlers. Enter must keep the dialog from submitting; the three
authoring gestures must remain cancellable and pass the current field name and
form for Field Embedding.
Advanced Branching Logic is intentionally focus-only. Its rendered handler must
continue to open the Logic workspace from that textarea and pass the live field
name as the target; do not add a click path merely to match other controls.

### Manual Online Designer regression: shared Choices/Calculation/SQL control

Before requesting review for a change to this shared control, use the Edit
Field dialog to verify all of the following:

1. For a new Radio, Drop-down, and Checkbox field, type and edit choice lines
   directly in the Choices textarea.
2. For an existing choice field, change the type to Calculated Field, then SQL.
   In each state, the textarea must be readonly and focus/click must open the
   corresponding authoring workspace rather than permit direct edits.
3. Without closing the dialog, change it back through a choice-owning type.
   The textarea must immediately accept direct edits again and must not launch
   an authoring workspace.
4. Make a valid choice edit, save the field, and reopen it to confirm that the
   choice text persisted. Do not save an intentionally incompatible temporary
   calculation or SQL value merely to exercise the transition.

The automated launcher test covers the same Radio → Calculated Field → SQL →
Drop-down → Checkbox state sequence and its `readonly`/`aria-readonly` values;
this manual check confirms the real dialog's focus, direct-edit, and save path.

Authoring-workspace controls are `rcDialog` components. Use native DOM events
and the bundled Font Awesome icons for their behavior and presentation; do not
introduce a jQuery UI dependency for a new control or icon state. Opt into the
shared fullscreen button with `fullscreenToggle: true`, rather than adding a
source-specific title-bar control. That toggle stores and restores its prior
geometry and disables drag/resize while fullscreen. An authoring source field
must remain `readonly` and return focus to a safe control when closing; never
leave a direct-edit fallback or focus loop behind the workspace.

When introducing an explicit field-embedding editor action, first confirm the
runtime invokes `Piping::replaceEmbedVariablesInLabel()` for that exact
metadata value. Current Online Designer hosts are Field Labels (including
ordinary Section Header fields), matrix Section Headers, Field Notes, and
individual Choice Labels. Keep the matrix Section Header on the rich-text
handoff. Choice Labels are `filter_tags()`-compatible one-line sources, so
preserve stored `<br>` tags and use the workspace's `space_or_br` line-break
policy rather than treating them as unrestricted rich text. Pass the current
instrument name for field-embedding completion; for a matrix header, obtain it
from the enclosing Add/Edit Field form because the matrix dialog is not inside
that form.
The shared expand/compress glyph is deliberately compact and vertically aligned
with rcDialog's close control; do not add workspace-specific styling to change
that title-bar geometry.
Consumers that need scripted control must use `ctx.setFullscreen(boolean)`
after `dialog:shown`, with `ctx.isFullscreen()` for state, rather than reaching
into `ctx._dialog`; this works even when the visible toggle is omitted.

Every `field.*` authoring-source call must provide its current raw
`targetField`. The workspace adds that variable name, and only that name, to
the title as a code-styled ` - [field_name]` token; the brackets alone use the
muted secondary color. It keeps the base title while a new field has not yet
received a variable name. Do not add the suffix to multi-field or non-field
sources.

When a source already has substantial runtime-owned reference material, keep
that endpoint as the authority. For SQL fields, load the project-scoped
`Design/sql_field_explanation.php` JSON response only when the workspace Help
tab is selected, render its trusted server-authored content there, and provide
a contained scroll area for long examples. Do not copy that guidance into the
workspace or open a second dialog.

## First establish the contract

Before changing a list or parser, write down the public spelling, parameters,
examples, output/value kind, supported authoring and runtime contexts,
permissions/configuration prerequisites, and failure behavior. Decide whether
the feature is public author syntax, an internal runtime helper, or both.

Check the relevant production implementation and add focused behavior tests
before treating a legacy use as supported. Do not broaden authoring support to
match a permissive legacy parser or a historic stored expression without a
deliberate compatibility decision.

## Source-of-truth map

| Feature | Runtime/source registration | Shared authoring metadata and help | Authoring transport | Key checks |
| --- | --- | --- | --- | --- |
| Smart variable | `Classes/Piping.php`: `Piping::getSpecialTagsInfo()` plus its replacement implementation | `Classes/AuthoringSyntax/Catalog/LogicSmartVariableCatalog.php` for Logic value kinds, source availability, and server-only dependency semantics; `Classes/AuthoringSyntax/Catalog/PipingSmartVariableCatalog.php` for Piping qualifier, record/event/form/user, record-or-public-survey, and form-or-repeating-event context, evidence-backed parameter contracts, named system-capability requirements, and runtime-recognized names omitted from legacy help; existing Smart Variables help is generated from `Piping` | `Controllers/DesignController.php`: `buildAuthoringSyntaxEditorCatalog()` | Piping replacement; Piping and Logic semantic diagnostics; PHP/JS parser fixtures if its grammar is new |
| Piping project-field modifier | `Classes/Piping.php`: field replacement implementation | `Classes/AuthoringSyntax/Catalog/PipingFieldParameterCatalog.php` for evidence-backed field-type, validation, and metadata contracts | `Controllers/DesignController.php`: `buildAuthoringSyntaxEditorCatalog()` | Piping replacement; catalog, PHP/browser semantic, and completion tests |
| Piping source capability | The concrete runtime path for the named source | `Classes/AuthoringSyntax/Catalog/PipingSourcePolicyCatalog.php` for evidence-backed record/event/form/user/public-survey/repeating-event context, recipient-dependent context, and delivery-mode support | `Controllers/DesignController.php`: `buildAuthoringSyntaxEditorCatalog()` and `diagnoseAuthoringSyntax()` | Runtime context behavior; PHP/browser semantic and completion tests |
| Piping system capability | The concrete replacement guard for a named runtime-availability capability, including a current-project gate where applicable | `PipingSmartVariableCatalog` definitions plus each affected variable's `required_system_capabilities` | `Controllers/DesignController.php`: `buildAuthoringSyntaxEditorCatalog()` transports only the named capability's enabled state and author-facing label | Runtime enabled/disabled behavior; PHP/browser semantic and completion tests; stale-catalog compatibility |
| Field Embedding | `Classes/Piping.php`: `replaceEmbedVariablesInLabel()` plus `Resources/js/DataEntrySurveyCommon.js`: `doFieldEmbedding()` | Pure PHP/browser `FieldEmbeddingSyntaxParser` plus `FieldEmbeddingSemanticAnalyzer`; draft-aware host occurrences, dependencies, and paginated-survey pages in the authoring catalog | `Controllers/DesignController.php`: `buildAuthoringSyntaxEditorCatalog()` and `diagnoseAuthoringSyntax()` | Runtime replacement and relocation behavior; PHP/browser semantic parity; completion and HTML text-node fallback tests |
| Special Function | `Classes/LogicParser.php`: public runtime allowlist and implementation/translation | `Classes/AuthoringSyntax/Catalog/LogicFunctionCatalog.php`; `Design::renderSpecialFunctionInstructions()` renders its reference from that catalog | `Controllers/DesignController.php`: `functions` catalog | Runtime evaluation/translation; catalog completeness; PHP/JS semantic parity |
| Built-in Action Tag | `Classes/Form.php`: `Form::getActionTags()` plus every runtime consumer that implements the tag | `Design/action_tag_explain.php`, name/description entries in `action_tags`, and `ActionTagCatalog` only for runtime-proven authoring properties | `Controllers/DesignController.php`: `action_tags` catalog, then `ActionTagSemanticAnalyzer` | `ActionTagParser`, `ActionTagCatalog`, and `ActionTagSemanticAnalyzer` PHP/JS fixtures; feature-specific runtime tests; Online Designer applicability |

External Module action tags are a separate case. Their manifests feed
`ExternalModules::getActionTags()` and are exposed by the project catalog and
Action Tags help automatically. The core must not add an EM-specific runtime
implementation or treat a module tag as a built-in tag.

The baseline Action Tag semantic scope is a project registration advisory.
`ActionTagSemanticAnalyzer` compares structurally valid, enabled tags with the
shared `action_tags` catalog, which merges
`Form::getActionTags()` and tags from External Modules enabled for that project.
It warns when a name is absent, without blocking the annotation or attempting
to infer any parameter, field-type restriction, or module-specific runtime
semantic. A missing catalog must remain structural-only for stale clients; an
explicitly empty catalog means no names are registered. Disabled tags do not
warn because they do not apply at runtime.

An enabled `@IF` wrapper condition is an embedded Logic expression, not an
Action Tag parameter contract. Because `Form::evaluateIfActionTag()` evaluates
that raw condition with the normal Logic runtime, the PHP and browser
`ActionTagConditionSemanticAnalyzer` products pass the structural parser's
condition text to the shared Logic parser and semantic analyzer, then map
findings back to the annotation offsets. This is syntax and metadata feedback
only: do not evaluate a condition, infer its selected branch, or analyze an
explicitly disabled `@.OFF.IF` container.

The Field Annotation editor must use the same enabled condition spans when it
offers completion: delegate an enabled condition to the standard Logic
completer so authors receive Logic fields, events, smart variables, functions,
and keywords instead of Action Tag suggestions. Keep true/false branches on
the normal Action Tag path and provide no Logic completion for a disabled
`@.OFF.IF` container. Completion is never evidence that a condition is true
and must not choose a branch.

Use the same enabled-only condition ranges for hover documentation. Map a
Logic field, smart variable, or special function in a recognized enabled
condition to the existing Logic metadata renderer and its exact annotation
range. Do not provide Logic hover inside a disabled `@.OFF.IF` container,
including its nested conditionals. Hover remains descriptive only; it does not
evaluate a condition or imply a selected branch.

For enabled conditions, retain the structural condition highlight and layer
the shared Logic lexer’s reference, function, literal, keyword, and operator
markers on their exact annotation ranges. Leave disabled `@.OFF.IF` containers
(including nested conditions) with structural presentation only. Highlighting
is lexical authoring feedback and must never evaluate a condition or select a
branch.

Keep Field Annotation Summary results distinct: the Action Tag structure tree
lists contained tags, while an `Enabled IF condition Logic` section aggregates
Logic references and function calls from enabled conditions, including enabled
nested containers. Exclude disabled `@.OFF.IF` containers and descendants.
The summary describes parsed authoring syntax only; it must not evaluate a
condition or select a branch.

Group enabled-condition Summary findings by their individual parsed condition,
with a top-level or true/false nested-path label and its own source link. Keep
that condition's references and function calls together. The grouping must
describe syntax nesting only and must not imply that any branch was selected.

Within each enabled-condition group, show compact source-linked badges only
for diagnostics whose complete browser ranges fall inside that condition. Keep
the global Syntax health list unchanged; badges add local context without
altering severity, diagnostic production, evaluation, or branch selection.

In the separate Structural analysis tree, show compact error/warning counts
beside an `@IF` condition only for existing diagnostics fully within its range.
Keep a nested finding on the nested node rather than duplicating it on an outer
condition, and leave zero-count conditions uncluttered. Selecting a nonzero
count must activate Edit and select its first source-ordered contained finding.
Counts must not add validation, evaluate a condition, or select a branch.
Cover these Structural interactions in the browser workspace test: click a
count, recognized tag name, parameter, disabled `@IF` name, and condition
text; assert Edit activation and ACE selection, and confirm dialog cleanup
removes the delegated listener. Include a multiline condition and assert its
exact cross-line ACE range, plus a non-BMP condition that proves browser UTF-16
source offsets map to the expected ACE columns. Also cover a nested enabled
`@IF` condition and assert its link selects its own range, not the outer one.

Render each enabled-condition Logic reference and function call as a source
link with its exact annotation range. Selecting it must activate Edit and
select the corresponding ACE token. In the Structural Action Tag tree, link
every recognized tag and `@IF` container name, including disabled entries,
displayed parameters, and displayed condition text to their exact ranges. Use
the same delegated source-navigation path as condition counts, leave malformed
candidates as plain structure content, and do not evaluate a condition or
select a branch.

For Field Annotation Summary, render any diagnostic with a valid browser
UTF-16 range as a source link to its reported ACE span. This includes enabled
`@IF` Logic advisories but must not change diagnostic severity, evaluate a
condition, or select a branch.

`ActionTagCatalog` adds deliberately narrow built-in exceptions. The shared
`Form::getValueInQuotesActionTag()` extractor proves that `@DEFAULT` needs an
equals sign and a nonempty single- or double-quoted value; `DataEntry` pipes
that value and deliberately does not apply the tag to a File Upload or
Signature field. The analyzer preserves a whitespace-only quoted value because
the runtime treats it as nonempty, and treats quoted JSON-looking text as a
quoted value because the extractor does. Its simple quote-delimited extractor
does not support escaped delimiter quotes, so those receive an advisory
warning. It does not infer restrictions for other target field types or
separately parse the quoted Piping contents.
`@PLACEHOLDER` uses the same quoted-value extractor and escaped-delimiter
constraint. `DataEntry` attaches its resulting HTML placeholder only to a Text
Box or Notes field, or to the visible input of an Auto Complete Drop-down or
SQL field. A normal Drop-down has no such input, so the editor warns only when
its current validation explicitly proves that Auto Complete is not enabled;
absent validation context remains advisory-free. `DataEntry` pipes placeholder
text only when it has a record context. The Action Tag catalog does not infer
that transport context or add any separate semantics to the quoted Piping.
`@SETVALUE` and legacy `@PREFILL` use the same runtime extractor and share the
File Upload/Signature exclusion. The catalog therefore gives them matching
quoted-value properties, while `@PREFILL` alone declares
`deprecated_replacement: '@SETVALUE'`. PHP and browser analyzers emit an
advisory `deprecated_action_tag` at the authored `@PREFILL` name but continue
to report the shared syntax and field-applicability diagnostics. The runtime
selects `@SETVALUE`'s value if both tags appear together; retain that collision
behavior rather than inventing an authoring restriction.
`@READONLY` has an explicit no-parameter contract. Its runtime consumes an
exact whitespace-delimited token, not a value; warn for assignments and
parenthesized arguments instead of treating a whitespace-dependent legacy
spelling as supported syntax.
`@READONLY-FORM` and `@READONLY-SURVEY` share that exact-token,
no-parameter shape. The Form variant is meaningful for every instrument's
Data Entry rendering. The Survey variant is meaningful only for an instrument
configured as a survey, so its catalog declares `requires_survey_form`; emit a
warning only when the current form is known to be non-survey, never when the
form context or a stale catalog cannot prove that fact.
`@HIDDEN`, `@HIDDEN-FORM`, and `@HIDDEN-SURVEY` follow the same exact-token,
no-parameter and form/survey contracts. Retain the runtime's `@IF` resolution
as separate behavior: the catalog validates only the active tag's explicitly
proven parameter and target-form properties.
`@HIDDEN-PDF` has the same no-parameter shape, but a distinct PDF-only runtime
surface. `PDF` applies it only after resolving `@IF`; do not infer a warning
from the Field Annotation editor's non-PDF context.
`@HIDECHOICE` uses the shared simple quoted-value extractor for a nonempty
comma-delimited choice-code list. `DataEntry` resolves Piping in that list and
uses it only for Checkbox, Radio, Drop-down/SQL, Yes-No, and True-False choice
renderers. Warn for a known different field type, but do not attempt to
validate choice codes that Piping may supply or reject matrix fields whose
runtime behavior is limited rather than absent.
`@SHOWCHOICE` shares that quoted-list and choice-field contract. Its runtime
replaces the `@HIDECHOICE` result with all unlisted choice codes, so retain the
documented precedence without emitting a synthetic conflict warning. Do not
add a matrix restriction: the runtime computes a per-field hide list, but its
matrix-header path has no matching `@SHOWCHOICE` support.
`@NONEOFTHEABOVE` instead uses `Form::getValueInActionTag()`: it accepts a
nonempty unquoted or quote-delimited comma-separated value, then filters
those codes against the current Checkbox field's choices before adding browser
behavior. It does not Pipe the parameter. Require its assignment and Checkbox
target, but leave choice membership to the runtime; retain the parser's
separate compatibility advisory for unquoted parameters.
`@RANDOMORDER` is a name-presence check in `DataEntry`, not an exact-token
extractor: assignments and parenthesized arguments continue to activate it,
but their contents are ignored. Model this with `parameter.kind: none` and
`ignores_parameter: true`, so the authoring warning is accurate without
claiming runtime rejects the tag. Its Checkbox, Radio, Drop-down/SQL, Yes-No,
and True-False renderers shuffle only when the target is not a matrix. A
cataloged `excluded_matrix_field` contract needs the Field Annotation launcher
to supply the current unsaved Matrix Group state to both local and server
semantic analysis; warn only when that state is known. Do not infer a
record-page availability restriction from the editor.
`@MAXCHECKED` uses the permissive `Form::getValueInActionTag()` extractor, then
casts its Checkbox-field assignment to an integer before handing it to the
browser selection limiter. It applies to Checkbox matrices too. Use
`requires_assignment`, `requires_nonempty_assignment_value`, and
`requires_positive_integer` together when a traced runtime/documented contract
requires a canonical positive integer, while accepting either quoted or
unquoted values that the extractor can read. The warning must remain advisory:
legacy fractions and text are coerced by runtime rather than rejected. Do not
add a matrix or import-context restriction where the renderer has neither.
`@MAXCHOICE` is a parenthesized map, not an assignment: use
`requires_parentheses`, `requires_nonempty_value`, and
`requires_choice_limit_map` with a `choice_limit_map` parameter. The latter
must mirror `Form::parseMaxChoiceActionTag()`: comma-delimited entries, each
with a nonempty code, one equals sign split, and a nonnegative numeric limit.
Preserve runtime support for zero and fractional limits; do not Pipe the map or
infer choice-code membership. Its count is dynamic and event-specific, and
save-time code rechecks it for concurrent requests, so authoring diagnostics
must validate only static shape and traced target field types.
`@MAXCHOICE-SURVEY-COMPLETE` is not merely a display alias: it shares the
choice-limit-map and target-field contract, but its runtime does nothing unless
the target instrument is a survey and counts only completed survey responses.
Give the catalog the same parameter and choice-field properties plus
`requires_survey_form`. Field Annotation can warn when its current form is
known not to be a survey, but must not predict completed-response counts,
validate map codes against metadata, or warn when both MAXCHOICE variants are
present: runtime rendering deliberately gives the survey-completion variant
precedence.
`@NOMISSING` proves why a no-parameter catalog property must not automatically
mean “parameter ignored.” The broad rendered-field matcher recognizes the tag
name before attached text, but shared `Form::hasActionTag()` consumers require
an exact space-delimited token for missing-code labels, exports, checkbox
pseudo-fields, and import validation. Use `parameter.kind: none` so attached
assignments or arguments receive the normal no-parameter warning. Its effect
also depends on the project’s parsed `missing_data_codes`; expose only the
boolean `has_missing_data_codes` in the editor catalog and use
`requires_missing_data_codes` to warn when it is known false. Do not add a
field-type restriction, and retain compatibility when an older catalog does
not supply the availability state.
`@NOW`, `@NOW-SERVER`, and `@NOW-UTC` demonstrate the converse. Their
browser-side consumer recognizes the row class and ignores appended values or
parenthesized arguments, so use `parameter.kind: none` together with
`ignores_parameter: true`. The editor should advise that the value has no
effect, not reject the otherwise recognized tag. Trace the actual consumer
before adding any target rule: `enableActionTags()` fills a blank literal
`input[name]` and does not gate on field metadata, validation, or branching.
Do not turn help's Text Box, import, page-lifecycle, or read-only descriptions
into an unproven catalog restriction. Preserve the distinct runtime timestamp
sources in documentation: browser-local for `@NOW`, page-render server time for
`@NOW-SERVER`, and browser time converted to UTC for `@NOW-UTC`.
The `@TODAY`, `@TODAY-SERVER`, and `@TODAY-UTC` family shares that exact
ignored-parameter contract. Its regular date branches use browser-local,
page-render server, and browser-to-UTC dates, respectively. Do not promote the
help's date-only wording into a field or validation restriction: the shared
runtime handles time validations before it reaches the `@TODAY` date branches.
Likewise, do not invent an `@NOW`/`@TODAY` collision warning without a traced
runtime conflict contract.
`@USERNAME` shows how a target identity can be a valid catalog property without
inventing a field-type rule. Its server-side renderer populates blank values
and skips the project record-ID field. Add `excluded_record_id_field: true`
only after exposing the existing `record_id_field` catalog value to both
analyzers' target-field context. Diagnose the exclusion only when both names
are known; batch editors and stale clients must remain non-restrictive. The
same renderer ignores attached values and parenthesized arguments, so pair that
property with `parameter.kind: none` and `ignores_parameter: true`. Do not
infer a Text Box, validation, respondent identity, pagination, blank-value, or
read-only rule: `@READONLY` is a separate runtime feature.
`@WORDLIMIT` and `@CHARLIMIT` are the model for numeric parameters whose
runtime acceptance is wider than a whole-number UI expectation. Their helper
accepts quoted or unquoted assignments, requires a PHP-positive numeric value,
and then casts that value to an integer. Model this with
`requires_positive_numeric`, not `requires_positive_integer`; document the
truncation rather than rejecting fractional syntax that runtime accepts. Both
also use `excluded_record_id_field`. Where runtime has a documented and exact
precedence branch, model the loser with `suppressed_by_action_tags`: here,
`@WORDLIMIT` is suppressed by `@CHARLIMIT`. This is not a generic symmetric
conflict rule. Finally, do not infer a Text Box/Notes field type simply from
help text: the runtime helper selects named inputs and textareas without using
metadata.
`@FORCE-MINMAX` is a separate range-enforcement contract. The rendered-field
matcher recognizes its name and the range-validation paths ignore an attached
assignment or argument, so model a supplied parameter as advisory-only with
`parameter.kind: none` and `ignores_parameter: true`. `DataEntry` changes the
range check from soft to hard only while building validation for a Text Box or
Calculated Field; `Records` makes the same field's configured minimum or
maximum an import error. The Field Annotation launcher must therefore pass its
live unsaved minimum/maximum presence as a boolean to browser and fallback
analysis. Warn only when a known target is not a Text Box or Calculated Field,
or when that live range state is explicitly false. Do not require a particular
validation type, infer a record-ID rule, or turn the tag's help prose into any
broader page, participant, or import-context restriction.
`@HIDEBUTTON` shows why the exact renderer branch, rather than its help text,
must define the catalog. `DataEntry` detects its name and ignores an attached
assignment or argument, then replaces the generated Now/Today control only in
the Text Box branches for explicit date, time, and datetime validations. Model
that with `parameter.kind: none`, `ignores_parameter: true`, and one allowed
Text Box field context containing the complete runtime validation list,
including legacy date/datetime names normalized during rendering. The Field
Annotation workspace already provides live type and validation to browser and
fallback analysis. Warn only when that known context cannot generate a
control; do not infer the project-wide button setting, page mode, or an
unknown/stale target restriction.
`@PASSWORDMASK` follows the name-presence pattern, not a value extractor: the
form-element matcher recognizes an attached assignment or argument but the
Text Box renderer uses only the tag name to select password input type. Model
that with `parameter.kind: none` and `ignores_parameter: true`. That renderer
does not run for any other field type, and the record-ID field is skipped or
converted to hidden first, so add the exact Text Box allowed context and
`excluded_record_id_field`. Warn only when the shared catalog and current
Field Annotation context prove either condition. Do not infer a validation
constraint, browser-autofill behavior, or a collision rule from an unreviewed
combination with another Action Tag.
`@RICHTEXT` demonstrates why a visible editor feature does not automatically
justify an applicability restriction. `DataEntrySurveyCommon` initializes the
toolbar only for Notes fields, but `Piping` and PDF formatting also inspect raw
`@RICHTEXT` text for Text Box or Notes values. The form-element matcher accepts
an attached assignment or argument, but neither path consumes it, so model only
`parameter.kind: none` and `ignores_parameter: true`. Do not add a Notes-only
or record-ID restriction, or infer enabled toolbar controls, attachment/image
availability, AI availability, survey access, or collision behavior without
separate runtime evidence.
`@CONSENT-VERSION` demonstrates a project capability that is safe to expose
per form without pretending to know a participant-specific result. The
`DataEntry` Text Box path recognizes its name, ignores any attached assignment
or argument, and assigns a version only when the value is blank on a survey
page. Build `forms[].has_econsent` from active e-Consent items by survey ID;
do not resolve the record/DAG/MLM-specific version in this catalog. Combine
`requires_survey_form`, `requires_econsent_enabled`, the existing record-ID
exclusion, and an allowed Text Box context. The analyzer must remain
non-restrictive for missing/stale e-Consent state and must not predict the
current version, field blankness, or submission outcome.
`@INLINE-PREVIEW` demonstrates a compound, target-local renderer contract.
The form-element matcher supplies its name even with an attached assignment or
argument, but the preview paths consume neither. `DataEntry` marks a File
Upload field previewable unless its validation is `signature` or
`enhanced_signature`; for a Descriptive field, the attachment renderer can
create a preview only when its metadata has an attachment. Represent those
alternatives with `allowed_field_contexts`: use `excluded_validations` for the
File Upload branch and `requires_attachment: true` for the Descriptive branch.
The analyzer may derive the current field's `has_attachment` from the shared
field catalog, but a missing/stale property must remain non-restrictive. When
one tag suppresses another only in one runtime branch, use a structured
`suppressed_by_action_tags` item with `names` and scoped
`allowed_field_contexts`; here `@INLINE` suppresses `@INLINE-PREVIEW` on a
non-Signature File Upload field, not on an attached Descriptive field. Do not
infer that a file value exists, that its extension is previewable, or that the
control can currently render.
`@INLINE` is a different File Upload-only renderer and illustrates a parameter
that is optional but meaningful only in one delimiter form. A bare tag and
empty parentheses use default sizing. `DataEntry` reads a nonempty
parenthesized comma-separated list, accepts PHP-numeric values without a
positive range or percentages in `(0, 100]`, and discards the whole list when
one component fails. An equals assignment is not consumed. Model this with a
tag-specific parameter validator rather than a generic positive-number rule;
the browser equivalent must mirror PHP's numeric acceptance. Preserve the
runtime's accepted but ineffective excess values as an advisory: the browser
image renderer reads only width and height. The File Upload selector also
includes Signature fields, so do not carry `@INLINE-PREVIEW`'s Signature
exclusion over. Do not make the current uploaded type/value, embedded-field
placement, or PDF sizing a static restriction.
`@LATITUDE` and `@LONGITUDE` demonstrate a paired name-only browser feature.
Their renderer and geolocation helper use the recognized name and a Text Box
only; neither consumes an assignment/argument nor requires numeric validation.
Give both tags `parameter.kind: none` with `ignores_parameter: true` and a
Text-Box-only `allowed_field_contexts` entry. When runtime precedence depends
on the same target context, use a structured `suppressed_by_action_tags` item:
`DataEntry`'s first branch and the browser's latitude test make `@LATITUDE`
win over `@LONGITUDE` on a Text Box. Scope that suppression to Text Boxes, and
do not infer browser permission, geolocation success, initial emptiness, or
save behavior from authoring context.
`@SAVE-PROMPT-EXEMPT` and `@SAVE-PROMPT-EXEMPT-WHEN-AUTOSET` demonstrate
name-only behavior with no static target restriction. Give each
`parameter.kind: none` and `ignores_parameter: true`: the former's row class
stops only that field from setting the page-wide unsaved-change flag, while the
latter skips only an initial automatic assignment to a blank field. Do not
claim that either tag disables saving or another field's prompt, and do not
require a companion auto-set tag: the runtime assignment can depend on Piping,
browser behavior, current page state, and the stored value.
`@LANGUAGE-CURRENT-FORM` and `@LANGUAGE-CURRENT-SURVEY` demonstrate a
surface-specific project capability. The `DataEntry` renderer recognizes their
names but does not consume an assignment or argument, then removes each unless
the target is an unvalidated Text Box, Radio Button, or Drop-down. Represent
that with `parameter.kind: none`, `ignores_parameter: true`, and two allowed
field contexts: `radio`/`select`, plus `text` with
`requires_no_validation: true`. As with other validation-dependent contexts,
an absent validation must remain non-restrictive while an explicit empty value
proves an unvalidated Text Box. Trace the browser consumer separately: it
applies the Form variant only on Data Entry and the Survey variant only on a
survey page. Build boolean per-form `has_multilanguage_data_entry` and
`has_multilanguage_survey` catalog properties from active MLM languages and
their `form-active`/`survey-active` settings, then use the named
`requires_multilanguage_context` property to warn only for known-false state.
In `DesignController`, call the actual
`MultiLanguageManagement\\MultiLanguage` class (or import it); there is no
global `MultiLanguage` controller class.
The Survey variant also needs `requires_survey_form`. Do not resolve the
current user's language, validate a dynamic Radio/Drop-down choice code, or
infer paginated-survey placement or survey-response-review behavior.
`@LANGUAGE-FORCE`, `@LANGUAGE-FORCE-FORM`, and
`@LANGUAGE-FORCE-SURVEY` show how one parameter contract can have different
runtime surfaces. `MultiLanguage::getLanguageForceActionTags()` extracts the
last matching quoted assignment, `Piping::replaceVariablesInLabel()` resolves
it, and MLM applies the result only if it is active. Use the documented,
nonempty quoted `language ID` assignment properties; do not statically resolve
a piped value or claim that a literal value is active. The unqualified variant
may apply on either Data Entry or Survey, so declare
`requires_any_multilanguage_contexts: ['data_entry', 'survey']` and warn only
when every applicable surface is known false. The Form variant needs the
existing Data Entry property even when the instrument is also a survey. The
Survey variant needs both `requires_survey_form` and the Survey MLM property.
Runtime scans every instrument field on Data Entry but only the current survey
page, then lets the last matching tag win; do not infer that cross-field/page
order without a dedicated, draft-aware page model.
`@LANGUAGE-SET`, `@LANGUAGE-SET-FORM`, and
`@LANGUAGE-SET-SURVEY` use a field value rather than a tag parameter. The
`DataEntry` renderer recognizes the names (and ignores any attached parameter)
but removes them unless the target is `radio` or `select`; the browser then
binds change handling and uses the selected code as a candidate language ID.
Model the family with `parameter.kind: none`, `ignores_parameter: true`, and a
Radio/Drop-down `allowed_field_contexts` entry. The unqualified tag uses
`requires_any_multilanguage_contexts`; the Form and Survey variants use the
matching single-surface property, and the Survey variant additionally requires
a survey form. Do not validate static choice-code membership or language
activeness: initialization and change handling depend on the current value and
MLM's runtime configuration. Likewise, do not infer cookie changes or
precedence among multiple tagged fields without a draft-aware rendered-page
model.
`@LANGUAGE-MENU-STATIC` demonstrates a feature whose field annotation is
page-level rather than field-level. `MultiLanguage::getTranslationSettings()`
looks for its name after `Form::replaceIfActionTag()` on the current survey
page, ignores PDFs, and sets a browser flag that matters only when the survey
has at least two active MLM languages. Model only the proven name-only form,
`requires_survey_form`, and a boolean per-form
`has_multiple_multilanguage_survey_languages` capability. Warn only when that
capability is known false. Do not add a target-field restriction, or infer a
current page, conditional `@IF` result, PDF context, or rendered-menu state.
The Mobile App `-APP` family is an example of a feature guarded by the runtime
registry rather than a per-field project capability. `Form::getActionTags()`
adds these names only when the system Mobile App feature is enabled, so the
existing project `action_tags` registration check already governs whether the
editor recognizes them. Do not add a second availability flag just to repeat
that guard. Their catalog contracts are all bare tags: `@APPUSERNAME-APP` and
`@BARCODE-APP` are Text Box/Notes-only, `@HIDDEN-APP` is unrestricted,
`@READONLY-APP` applies only to editable mobile controls, and `@SYNC-APP` is
File Upload/Signature-only. Native-client outcomes—identity, camera access,
scan result, upload state, and rendering—remain outside static analysis. When
the consumer is outside the PHP/web Core tree, document that boundary and
derive only the field and parameter constraints the maintained runtime
contract proves; do not use a client-specific behavior to invent server-side
context rules.
MyCap has a separate setting guard in `Form::getActionTags()` (project setting
with a system fallback), which likewise belongs to the existing action-tag
registration catalog rather than a new per-field capability. Trace the
MyCap API's metadata conversion, not only its help text: that runtime proves
that `@MC-FIELD-FILE-IMAGECAPTURE` is a bare File Upload-only marker and that
`@MC-FIELD-FILE-VIDEOCAPTURE` is a File Upload-only marker with a bare default
form or optional unquoted `duration:audio-mute:flash-mode:device-position`
settings. Preserve its case-insensitive named values and optional slots, but
warn where an authored option list cannot produce those settings rather than
endorsing a silent default fallback. `@MC-FIELD-HIDDEN` is a bare
any-field marker. The `@MC-FIELD-TEXT-BARCODE` help entry says Text Box/Notes,
but current `ProjectHandler::processAnnotation()` attaches barcode settings to
every non-skipped converted field. Do not introduce a Text/Notes diagnostic
until that runtime adds the corresponding guard; document intended native use
separately from a restriction the actual metadata converter does not enforce.
The registered MyCap participant metadata tags require a different evidence
path from the capture tags. `@MC-PARTICIPANT-CODE`,
`@MC-PARTICIPANT-JOINDATE`, `@MC-PARTICIPANT-JOINDATE-UTC`, and
`@MC-PARTICIPANT-TIMEZONE` are found by the Data Entry matcher and participant
helpers by name; their assignment/argument text is not consumed. Model this as
an ignored-parameter warning, not a malformed-tag error. The participant
write helpers explicitly skip the record ID, which justifies that one target
diagnostic. Their Text Box and datetime defaults are created by repair/setup
helpers, while the runtime lookup and save paths accept annotated fields
without a type or validation guard. Do not add a Text Box or date-validation
requirement until a runtime consumer enforces it. Keep participant
creation, join timing, values, and save success outside static analysis.
MyCap active-task result annotations are an exception to the usual
`Form::getActionTags()` registration source. Gate their supplemental catalog
entries on ordinary MyCap registration so a project without MyCap still
receives the normal unknown-tag advisory. There are two acceptable evidence
paths. `ProjectHandler::processAnnotation()` proves the 32 provider-info
annotations (`AMS`, `AUD`, `FIT`, `REA`, `REC-AUD`, `RMO`, `SEL`, `SHO`,
`SPR-AUDIO`, `TIM`, and `TWO`). Separately, a current `ActiveTasks` class's
`getFormFields()` method plus `ResultHandler::saveResult()` and
`ProjectMapper::fieldMap()` proves the 12 generated result annotations: four
`HOL` names, `PSA`, `SPA`, `SPR-TRANSCRIPTION`, `SPR-EDITED-TRANSCRIPTION`,
`STR`, `TON`, `TOW`, and `TRA`. The latter save path adds each received result
key to its map and resolves the exact whitespace-delimited annotation; it does
not require provider info. Do not add every `Annotation::TASK_ACTIVE_*`
constant merely because it exists: `DBH`, `SIN`, `VAU`, and both `VCT` names
lack both current evidence paths. Both supported groups are bare,
whitespace-delimited names and are useful only on an enabled MyCap task form.
Neither runtime path provides a target-type or active-task-format guard, so do
not invent one. Do not infer the active-task setup, client/provider behavior,
multi-annotation precedence, data, or uploads. Document this as a controlled
registration exception until catalogs become the primary legacy Action Tag
source.
These annotations are generated into result-field metadata by MyCap task setup,
not selected through a user-facing task-configuration control. Keep their
editor hover description explicit: “Auto-generated MyCap active-task result
annotation. Do not modify manually.”
Use `suggest_in_editor: false` for this kind of recognized-but-not-manually-
authored annotation. The semantic catalog must retain it so existing generated
metadata does not receive an unknown-tag warning, while generic completion
must omit it rather than inviting authors to add it.
When a catalog description carries an authoring warning, cover its normal
parsed-token-to-hover rendering path in the browser workspace test; testing the
semantic analyzer alone does not verify the user-facing notice.
For the required MyCap task-result family, inspect both the task repair helper
and the result save path. `@MC-TASK-UUID`, `@MC-TASK-STARTDATE`,
`@MC-TASK-ENDDATE`, `@MC-TASK-SCHEDULEDATE`, `@MC-TASK-STATUS`,
`@MC-TASK-SUPPLEMENTALDATA`, and `@MC-TASK-SERIALIZEDRESULT` are exact bare
annotations. `ProjectMapper::save()` proves an enabled task-form requirement:
it rejects a task row unless `enabled_for_mycap` is `1`, so expose that
read-only fact as a per-form catalog property and warn only when it is known
false. `Task::getFormFields()` offers canonical Text Box defaults for UUID and
dates, a Drop-down for status, Notes for supplemental JSON, and File Upload
for the serialized result, but it is a repair/default helper—not proof that
the generic annotation mapper rejects alternative types. Do not add field-type
rules for the first six without such a guard. The separate `ResultHandler`
file-upload loop does prove that `@MC-TASK-SERIALIZEDRESULT` needs a File
Upload field to receive the serialized result, so that one diagnostic is
appropriate. Do not model schedules, result values, a participant, or a
successful transfer as authoring facts.
The same task setup and repair process writes all seven annotations onto the
form automatically, so mark each `suggest_in_editor: false` while retaining
its diagnostics contract. Do not rely on the repair helper's attempted “MyCap
App Fields - Do Not Modify” header as an authoring fact: the UUID it creates
also carries `@HIDDEN-SURVEY`, which fails that helper's exact UUID comparison.
`@DOWNLOAD-COUNT` uses `Form::getValueInParenthesesActionTag()` to read one
target name, then removes brackets and literal spaces before looking up exact
project metadata. Accept both the documented bare field name and its bracketed
form inside nonempty parentheses; reject event/instance-qualified expressions,
lists, and quoted text that cannot become that one runtime lookup. A known
target must be a File Upload field or a Descriptive field whose metadata has an
attachment. Expose that attachment state as `has_attachment` on the shared
field catalog, and warn only when a current catalog proves the name unknown or
not downloadable. Do not infer a Text Box/Notes restriction for the counter
field: the browser incrementer selects any matching named form control. The
same-event/repeating-context requirement and whether a rendered link is
actually downloaded are dynamic runtime behavior, not static editor rules.
`Calculate::buildCalcTextEquation()` proves that `@CALCTEXT` extracts a
nonempty parenthesized Logic expression only for a Text Box field.
`Calculate::buildCalcDateEquation()` proves the same shape for `@CALCDATE`
and additionally requires a date or datetime validation. The Field Annotation
workspace sends its current unsaved field type and validation to both browser
and server analysis, so those rules remain correct while a field is being
edited. Each finding is a warning because legacy metadata may save a form that
does not become a working calculation. Do not extend these properties to an
unreviewed Action Tag, and do not infer the same rules for an External Module.

## Planned catalog ownership and External Module schemas

The current catalogs deliberately enrich existing runtime registries; they do
not yet replace them. The intended direction is for the new authoring catalogs
to become the original structured sources of truth. At that point,
`Piping::getSpecialTagsInfo()` should generate its current presentation output
from the Smart Variable catalog (or be retired in favor of a structured API),
and equivalent runtime/help consumers should derive their views from the
Special Function and Action Tag catalogs. Do not make that inversion until the
catalog can represent every runtime, availability, documentation, and
compatibility detail its consumers require.

**Putative first post-initial-completion step (deferred):** design structured
authoring support for External Module-provided Action Tags. It must not start
until built-in coverage is complete and its extension contract is deliberately
settled. The design may combine a documented declarative `config.json` schema
with a controlled hook/callback for module-provided catalog entries and/or
parameter validation; decide those responsibilities, safety boundaries, and
transport before exposing either mechanism. The EM framework will then need to
validate and expose the selected metadata through
`ExternalModules::getActionTags()`. Until that architecture exists, an EM tag
remains structurally recognized and documented, but its module-specific syntax
and semantics remain the module's responsibility.

When either architecture change is implemented, revise this manual before the
same PR is opened: replace the source-of-truth map and the per-feature steps,
then add migration and compatibility checks for every former registry
consumer. The pre-PR checklist must continue to name the authoritative catalog
rather than preserving obsolete transitional instructions.

**TODO (after catalog coverage is complete):** Reorganize the authoring
catalogs into a per-item property model—`item -> [properties]`—so qualifier,
parameter, context, availability, documentation, and dependency metadata is
not scattered across parallel maps. Refactor the catalog accessors, controller
transport, semantic analyzers, completion code, help renderers, and tests in
the same change. Do not start this consolidation while catalog coverage is
still expanding, because it would turn ongoing runtime-evidence work into a
moving migration target.

## Add a Smart Variable

1. Implement the variable's actual replacement behavior in `Piping` (or the
   narrowly scoped feature service it calls). Register its public syntax,
   description, examples, and conditional availability in
   `Piping::getSpecialTagsInfo()`. Preserve any project/configuration guards in
   both registration and evaluation.
2. Verify normal, missing-context, permission/configuration-disabled, and
   malformed-parameter behavior. If it accepts parameters, test omitted,
   valid, and invalid parameter combinations; parameterized variables must not
   silently gain meanings through autocomplete.
3. If the spelling or bracket/parameter grammar is novel, update both
   `PipingSyntaxParser.php` and `Resources/js/AuthoringSyntax/PipingSyntaxParser.js`
   and add matching cases to
   `UnitTests/AuthoringSyntax/Piping/piping-syntax-fixtures.json`. Keep the
   structural parser independent of project metadata.
4. For use in Logic, add the base variable to
   `LogicSmartVariableCatalog` with its conservative `value_kinds`. Set
   `allowed_source_kinds` to the reviewed source list. Use `['*']` only after
   confirming that existing behavior is valid in every current Logic source;
   do not invent restrictions from intuition. A value whose type cannot be
   safely inferred remains `['mixed']`. If the smart variable derives from
   project fields, also declare its machine-readable dependency semantics so
   server-side calculation-cycle analysis can retain the edge as potential.
   The Online Designer uses those semantics after a calculation save to show a
   warning-only cycle path; any new dependency kind needs a fixture covering
   both its graph edge and its potential/definite classification.
5. Characterize Piping event and instance qualifiers independently. If the
   replacement implementation consumes either qualifier, declare the matching
   `supports_event_qualifier` and/or `supports_instance_qualifier` values in
   `PipingSmartVariableCatalog`; do not embed an allowlist in a semantic
   analyzer or UI completer. The core default is `false` for both properties.
   Record any parameter only when its runtime contract is evidence-backed:
   instrument targets, project-owned targets (such as a unique dashboard or
   report name), format-only opaque IDs, enumerated values, and unrestricted
   free text are distinct kinds. Do not require an otherwise documented
   parameter when a feature-specific renderer can validly inject or replace it
   before normal Piping runs; record that source distinction and add a
   source-specific check only when its integration is also cataloged.
   A project-owned target scoped to the current project needs a read-only
   top-level catalog collection from `buildAuthoringSyntaxEditorCatalog()` for
   completion and validation; omit diagnostics when that collection is absent
   so a stale catalog remains compatible. Do not use a metadata getter with a
   write/backfill side effect merely to construct the catalog. A cross-project
   target or any target whose complete collection would be unnecessarily broad
   or sensitive must instead use a narrow source-bounded AJAX diagnostic lookup:
   send only identifiers already present in the expression, return only the
   resulting findings, retain immediate local checks in both analyzers, and
   cancel or disregard stale browser requests. Do not enumerate target titles
   or a global catalog merely to support validation. Unknown instrument values
   that may fall back to the current form must remain warning-only; an unknown
   target with no runtime fallback may be an error. If the implementation
   accepts a fixed number of parameters, also declare `max_parameters`; excess
   parameters are a warning-only diagnostic, never a runtime compatibility
   change. Do not model the runtime's permissive colon-delimited numeric or
   `*-instance` parsing as author syntax, except for an explicitly documented
   parameter contract in the replacement case itself. For example,
   `[rand-number:2]` is Randomization's documented sequence-reference
   parameter, while the runtime's other numeric shuffling remains outside the
   authoring grammar. Mark qualifier-reviewed variables with
   `rejects_legacy_inline_instance_qualifier` so semantic analysis rejects
   `[survey-url:followup:last-instance]` and requires
   `[survey-url:followup][last-instance]`. Keep the structural parser opaque
   for variables with legitimate numeric parameters, such as
   `[data-table:435]`, and do not mistake a value in a cataloged `free_text`
   parameter position (for example, `[dashboard-link:my_dashboard:2]`) for an
   inline instance. For an
   event-qualified reference to a known
   project instrument, the shared event-form designation is also an advisory
   check: keep an instrument outside that event available but muted in
   completion and warn in semantic analysis. Add PHP/browser semantic fixtures
   for every supported and explicitly unsupported form. If a Piping smart
   variable's replacement explicitly consumes a record, event, form, or user, set
   its `requires_record_context`, `requires_event_context`, or
   `requires_form_context` or `requires_user_context` capability. When a variable can resolve through
   either a record or a documented public-survey route, use
   `requires_record_or_public_survey_context` instead. Do not infer this from
   its name, and catalog any project-specific public form transported by the
   source policy (for example, Twilio's public `[survey-url]`, whose runtime
   target is the project's `firstForm`). When a variable can use either the
   current form or a particular cataloged instrument parameter, use
   `requires_form_or_instrument_parameter` for that explicit fallback (for
   example, `[survey-title:survey_form]`). The semantic layer must allow the
   valid parameter in a form-less source while warning when neither route can
   resolve. Survey response timestamps and durations are a representative
   combined contract: their first parameter can select the survey form, while
   their replacement still separately needs record and event context. Do not
   turn a missing participant response into an authoring diagnostic.
   `[form-url]` and `[form-link]` use the same capability with a target that
   may be any project form, so the completion prompt must say `instrument`,
   not `survey instrument`; they also separately consume record and event
   context. A parameter may have `diagnose_unknown: false` only where the
   runtime has a proven fallback. For `[form-link]`, its first parameter can
   be custom link text when a current form exists, but cannot resolve a target
   in a form-less source, where the capability check must still warn.
   If a survey variable derives its result from a generated survey link and
   the runtime also supports the recordless public first-survey route, use
   `requires_record_or_public_survey_context` alongside its event and
   form-or-instrument requirements. The form-or-instrument check must treat
   that documented public route as a target only in a recordless public source;
   completion should neither request an unnecessary parameter nor offer a
   different known survey. `[survey-url]`, `[survey-link]`, and
   `[survey-access-code]` are the reference cases. A `diagnose_unknown: false`
   link-text shorthand must still be diagnosed when the source lacks the
   current form that makes the fallback possible; it is not a public-survey
   target.
   A distinct bare-variable participant route is likewise explicit:
   `supports_survey_participant_context` is appropriate only where the
   replacement can derive the exact target from the supplied participant. For
   `[survey-url]`, `[survey-link]`, and `[survey-access-code]`, that requires
   no first instrument parameter or event/instance qualifier. The separate
   form values `[instrument-name]`, `[instrument-label]`, and `[survey-title]`
   derive the current form from the participant; the first two accept no
   parameter, while a `survey-title` instrument parameter is its independent
   metadata-target route. The semantic layer must suppress only the applicable
   record/event/form findings in a source that explicitly declares
   `has_survey_participant_context: true`, while retaining those findings for
   an expression such as `[survey-url:some_survey]`, whose target no longer
   matches the supplied participant.
   Do not use this fallback for a record-bound response lookup merely because
   it takes the same instrument parameter: `[survey-return-code]` still
   requires a record. Add public-route, record, event, target-form, and
   parameter-completion fixtures for each distinction.
   If a bare repeat-instance Smart Variable needs the current form but can
   instead read a repeating current event, use
   `requires_form_or_repeating_event_context`. A named source may claim that
   alternative only with an evidence-backed `has_repeating_event_context`
   value. Transport project-dependent repeat state (for example, a renderer
   fixed to the project's first event) rather than treating any longitudinal
   source as repeating. Keep this distinct from `[field][last-instance]` and
   related structural instance qualifiers, whose runtime behavior belongs to
   the following field reference.
   When a Smart Variable's truth value instead depends on the global runtime
   page, use `truthy_runtime_page` rather than inventing record/event/form
   context. Add a matching `piping_runtime_page` source-policy value only when
   that source always renders on one proven page; then warn and mute the
   variable if its known output is fixed to `0`. Do not declare a page for a
   source that can render through multiple routes.
   If a Smart Variable's replacement reads only the current record (including
   a current-record Data Access Group lookup), set `requires_record_context`.
   If it ignores the structurally accepted event/instance qualifiers and has
   no colon parameters, record those three facts together through
   `QUALIFIER_CAPABILITIES`, an empty `PARAMETER_DEFINITIONS` entry, and a
   zero `MAX_PARAMETER_COUNTS` entry. This lets completion and diagnostics
   distinguish a recordless source from a deliberately ignored qualifier or
   an invalid authored parameter.
   Apply the same combined contract to current-event values whose replacement
   reads only the incoming event ID. Verify the preprocessor's per-reference
   event handling before declaring an event qualifier supported: structural
   acceptance alone does not mean that a replacement consumes it.
   Relative-event names need the same distinction: catalog their bare
   Smart-Variable replacement independently of their documented structural
   role as a dynamic event selector before a following Piping reference. Test
   whether a missing current event resolves blank or a runtime helper supplies
   a fallback before setting `requires_event_context`.
   For user-derived values, distinguish the trusted supplied-user or `USERID`
   fallback from the variable's no-user result. Catalog `requires_user_context`
   when it produces an empty or otherwise unusable value without either route,
   but keep the resulting diagnostic advisory because other runtime sources
   may legitimately provide the fallback.
   Do not invent a record/event/form/user requirement for a replacement that
   reads only the active project or application configuration. Catalog its
   zero-parameter and ignored-qualifier behavior, and verify that it remains
   available in every project-scoped authoring source.
   When a family delegates an unbounded parameter tail to a shared runtime
   helper, model only a helper contract that can be proven for every member.
   For example, `supports_report_filter` records that the first uppercase
   `R-…` token after the field-list argument of aggregate functions, Smart
   Charts, and Smart Tables selects a project report and short-circuits other
   filters. Analyze and complete that token using the read-only `reports`
   collection; do not infer contracts for the helper's remaining DAG, event,
   record, or chart-specific tokens until separately characterized.
   If the runtime replacement is explicitly guarded by an installation-wide
   feature and returns empty output when that feature is disabled, attach the
   named system capability rather than encoding that condition in a source
   policy. Follow the system-capability procedure below; a help-only or UI-only
   registration guard is not evidence that replacement has the same behavior.
   When the legacy reference hides a runtime-recognized variable but normal
   Piping still accepts it, list it through
   `getLegacyReferenceOmittedSmartVariables()` and inject its controller entry
   without `required_system_capabilities`; it must remain active rather than
   receive an invented availability warning. For example,
   `[dashboard-access-code]` is hidden from legacy help when public dashboards
   are disabled, but its replacement remains active and is therefore cataloged
   this way rather than as a public-dashboard capability.
6. Confirm `buildAuthoringSyntaxEditorCatalog()` carries it to both browser
   analysis and the server fallback. This is automatic for values registered
   in `Piping::getSpecialTagsInfo()`: `PipingSemanticAnalyzer` will then
   recognize the base name, while `LogicSemanticAnalyzer` also consumes its
   typed or restricted Logic metadata. Add a test for either analyzer when
   changing its supported availability or semantics. Do not add unreviewed
   parameter or source-specific Piping diagnostics until their runtime
   behavior has a complete catalog contract.
   A direct record guard or helper precondition is sufficient evidence for a
   `requires_record_context` rule even when no feature setting is checked. For
   example, MyCap participant Piping variables require a record, whereas the
   MyCap project-code variable does not; neither receives a fabricated
   MyCap-enabled availability capability.
   When a replacement has a distinct, verified alternative context, model the
   alternative rather than weakening its record requirement globally. For
   example, `[survey-queue-url]` and `[survey-queue-link]` can convert a
   numeric survey participant ID into a record, so they use
   `requires_record_or_survey_participant_context`. A known source policy may
   satisfy that only with an explicit `has_survey_participant_context: true`;
   omission is intentionally not treated as support. This prevents a newly
   cataloged source from accidentally claiming the fallback merely because it
   has no record.
   This record-or-participant route is distinct from the bare-target survey
   route described by `supports_survey_participant_context`: the latter does
   not turn an explicit instrument parameter into a participant-supported
   target.
   Add a source policy only when the declared runtime context is invariant for
   every use of that authoring surface. A recipient-dependent sender such as
   the bulk Survey Invitation composer must not claim one
   `has_record_context` value when its selection can mix record-backed and
   recordless participants. Instead, list only the non-invariant contexts in
   `recipient_dependent_contexts`; the opener must derive each from the live
   selected list as `guaranteed`, `partial`, or `unavailable` and pass that
   narrow map as `pipingContextAvailability`. The workspace transports the
   same map as `piping_context_availability` to browser analysis and the
   server fallback. `partial` must preserve completion and warn at completed
   direct dependencies; `unavailable` retains ordinary restricted-source
   behavior. Omit a context state when no current selection can prove it.
   If Piping runs on a fixed endpoint rather than the screen that opened the
   editor, declare its exact `piping_runtime_page`. This lets page-state
   Smart Variables such as `[is-survey]` and `[is-form]` be diagnosed from the
   evaluation route, not from an assumed surrounding page.
7. Verify the Smart Variables reference dialog, completion, hover information,
   and every intentionally supported source policy. Do not enable field
   completion in a source that has no per-record replacement context.

## Add or change a Piping field modifier

1. Characterize the runtime replacement behavior in `Piping` first: exact
   spelling, applicable field types and validation types, its interaction with
   other modifiers, and whether an unsupported spelling is ignored or changes
   output. Do not infer applicability from its name.
2. Add only the evidence-backed field contract to
   `Classes/AuthoringSyntax/Catalog/PipingFieldParameterCatalog.php`. Its
   field-local definitions drive completion, while its complete known set lets
   the semantic analyzers distinguish a known-but-inapplicable modifier from
   an unknown runtime modifier. Leave the latter non-diagnostic until its
   contract is established. Where the runtime gives one modifier precedence
   over another or cannot meaningfully combine them, declare their shared
   `exclusive_group` instead of reproducing that interaction in a completer.
3. Confirm `buildAuthoringSyntaxEditorCatalog()` emits both the field-local
   `piping_parameters` and the top-level `piping_field_parameters` set. Do not
   duplicate field-type logic in a browser completer or either semantic
   analyzer.
4. Add shared PHP/browser semantic fixtures for a supported field, a
   known-but-unsupported field, an unknown modifier, and each cataloged
   mutually-exclusive combination. Unsupported or incompatible use must be a
   warning unless runtime rejection is conclusively established. Add catalog
   and completion coverage, including the runtime-sensitive spelling.
5. Verify the Piping editor's completion, hover/help text, PHP server fallback,
   and browser analysis agree. Run the Piping parser, catalog, semantic, and
   authoring-workspace tests before opening the PR.

## Add or change Piping source capability

1. Establish the named source's actual replacement context. In particular,
   verify whether a project record, event, form, user, public-survey route, or
   repeating-event alternative is
   available and set the corresponding `has_record_context`,
   `has_event_context`, `has_form_context`, `has_user_context`, and
   `has_public_survey_context` properties accordingly. Use
   `has_repeating_event_context` only when the exact event passed to Piping is
   known to repeat. If the public route is
   limited to one project form, transport that form as `public_survey_form`; do
   not infer any of these facts from the source's UI or from general
   smart-variable support.
   If Piping itself runs only for specified runtime delivery types, list those
   values as `piping_delivery_types` and have the authoring opener pass its
   current selection as `pipingDeliveryType`. An absent selection remains
   compatible, while a known unsupported selection must warn and mute Piping
   completion rather than reject the source text.
   When one template is rendered independently for selected recipients, do
   not turn the record/event/form distinction into a fixed boolean. Verify the
   exact branches, declare the variable contexts in
   `recipient_dependent_contexts`, expose the minimum per-recipient provenance
   needed by the authoring opener, and have it return only `guaranteed`,
   `partial`, or `unavailable` values through `pipingContextAvailability`.
   A selection with no recipients must omit those states rather than invent a
   restriction. The server fallback receives the same JSON map as
   `piping_context_availability`; validate its values before semantic analysis.
2. Add a `PipingSourcePolicyCatalog` entry only for an evidence-backed
   restriction. An absent entry preserves existing behavior. A recordless
   source prohibits project-field completion and affects only smart variables
   with matching cataloged `requires_*_context` properties; it must not imply a
   broad Smart Variable allow-list. A shared resolver can establish an
   invariant policy even when its callers differ: for example, MyCap's
   participant display-label resolver always supplies record and event but no
   form or survey participant. Do not convert caller-dependent values such as
   a `USERID` fallback or repeating state into fixed policy properties. The
   same caution applies to a shared renderer used from multiple routes, such
   as `DataEntry::getRecordCustomEventLabel()` for custom event labels.
   A source may have a public entry route without being recordless at the
   point Piping runs. For example, `Surveys/index.php` evaluates a configured
   end-of-survey redirect only after a response record exists and passes its
   record, event, and current form in both redirect branches. Model those
   guaranteed contexts and the fixed `surveys/index.php` page, but do not
   infer a participant, user, or repeating context from the public route.
   Conversely, one shared Piping implementation can have several caller
   routes. `Survey::sendSurveyConfirmationEmail()` always passes its completed
   record, event, and configured form for both template parts, but it is
   called from survey, Twilio, PROMIS, Data Entry, and a follow-up endpoint.
   Give the subject and content one shared source kind for the invariant
   context, and leave user, repeating, public-survey, and runtime-page
   properties absent when they differ by caller.
   If one caller supplies less context than the rest and the source UI cannot
   identify that caller, do not turn that path into a global denial. Survey
   acknowledgement text always has record/event context and a fixed survey
   endpoint, but its Twilio call omits the form argument used by web
   completion. Its policy declares the invariant record/event/page facts and
   leaves form context absent, preserving normal completion without promising
   that every delivery mode produces the same value.
   Also trace source settings that alter the Piping call's useful data, not
   just its formal arguments. The web-only stop-action acknowledgement always
   supplies event and form, but its optional delete-response setting can
   remove the record first. Declare the invariant event/form/page state and
   leave record context absent unless the authoring opener can safely expose
   that configuration-specific choice to the analyzer.
   Do not add a policy solely because one runtime branch is well understood.
   Survey Instructions illustrate the limit: web rendering supplies event and
   form but may have no public-response record, while Twilio either does not
   run Piping yet or runs it without a form. Survey PDF generation is a third
   path: it supplies a form, but blank PDFs have neither record nor event.
   With no editor-side indication of the delivery and record state, a source
   policy would mislabel valid, literal, or blank output. Record the audit and
   leave the policy absent until the transport can distinguish the alternatives.
   The same discipline applies where a translated value is visibly associated
   with an editor-supported setting but uses a different authoring surface.
   Survey Queue custom text is one example: its default setting has the
   `survey_queue_custom_text` workspace policy, while language-specific
   translations are edited through Multi-Language's separate rich-text
   control. `MultiLanguage::getSurveyQueueSettings()` pipes those translations
   from the caller's `Context`: the standalone Queue provides a record only,
   acknowledgement branches provide record/event/form details, and the
   post-completion AJAX branch provides none. Do not reuse the default policy
   or add a translation-specific one until that editor can identify the exact
   rendering route.
   Standard field-metadata editors are also deliberately free of a policy that
   infers a record or delivery channel. `field_label` includes field labels,
   choice labels, and section headers; `field_note` is the ordinary Field
   Note. These values are authored outside a record and are evaluated later in
   the displayed record's context. The normal `DataEntry::renderForm()` path
   supplies record/event/instance/form values; public Survey pre-creation
   behavior, Twilio's channel-specific implementation, and blank PDFs must
   not restrict the shared editor. Blank PDFs do not perform this Piping
   replacement.
   The editor openers provide the containing form as
   `fieldEmbeddingFormName`, which is also transported to Piping semantic
   analysis as `form_name`. Each cataloged form now carries a design-time
   `repeat_context`: `guaranteed`, `partial`, or `unavailable`, derived with
   `Project::isRepeatingFormOrEvent()` across the events to which that form is
   designated. This is deliberately not record availability. In a standard
   field editor, the standalone `[previous-instance]`, `[current-instance]`,
   `[next-instance]`, and `[new-instance]` Smart Variables are muted and
   warning-only when the form never repeats, and remain available with an
   advisory when it repeats only in some designated events. `[first-instance]`
   and `[last-instance]` remain unchanged because the runtime has their
   distinct non-repeating instance-1 fallback. The separate runtime audit of
   field-reference qualifiers found that this form profile cannot safely guide
   them: `pipeSpecialTags()` can normalize `previous`/`current`/`next` from the
   enclosing rendered form, event, and instance before resolving the referenced
   field, while `replaceVariablesInLabel()` deliberately treats a numeric
   suffix on a non-repeating target as that target's ordinary value.
   `first`/`last`/`new` take still different target-form paths. Consequently,
   leave field-reference instance completion and diagnostics unchanged in the
   shared editor; do not derive warnings from the edited form, restrict generic
   Piping completion, or modify Twilio. Reconsider only for an authoring
   surface that can prove its exact runtime form/event contract.
   A source may still support a useful partial policy when every branch calls
   Piping with some invariant arguments. Offline Instructions always run on
   the survey endpoint with event and form, but a public offline page has no
   record while a private participant may have one. Declare only event/form/
   page state; preserve field completion and leave record, participant, user,
   public-survey, and repeating state absent rather than imposing the public
   branch's limitation globally.
   A shared template pair should also share its source kind when one runtime
   method pipes both at the same point. Automated Survey Invitation subject
   and content are prepared by `SurveyScheduler::scheduleParticipantInvitation()`
   with record, event, instance, form, and generated participant ID. Declare
   those invariant contexts once; do not infer a sender user or runtime page
   from a scheduler that may run during an interactive save or background
   processing.
   A shared project setting may likewise have a core-wide renderer with
   caller-specific pre-processing. `getCustomRecordLabels()` always pipes
   each displayed record with an event (the first in that record's arm), but
   Data Entry callers can first provide form, user, and repeat details that
   generic record lists do not. Its policy should declare only the invariant
   record/event facts and the absence of participant/public-survey routes;
   leave the caller-dependent contexts unclaimed.
   Data Entry Trigger URL follows the same shared-renderer rule. Its helper
   pipes after every saved record with record and event context, including a
   Data Comparison merge. Normal form and survey saves pass an instrument,
   but the merge path does not. Declare the shared record/event facts, not a
   universal form, user, repeat, or page context.
   e-Consent Custom Label is a different shared-PDF case. Its only renderer,
   `Econsent::getCustomEconsentLabel()`, always passes the e-Consent
   instrument to `Piping::replaceVariablesInLabel()`, so it can declare form
   context. The generic PDF renderer also creates blank PDFs, where neither a
   record nor event exists. Declare the invariant form plus the absence of
   survey-participant/public-survey alternatives, but leave record, event,
   user, repeat, and page state absent; do not turn blank-PDF behavior into a
   global denial of the normal record-backed e-Consent header use.
   Custom Repeating Instrument Label has the complementary record-backed
   repeating-form case. `RepeatInstance::getPipedCustomRepeatingFormLabels()`
   passes every rendered label's record, event, form, and exact instance to
   Piping. It returns before replacement for a whole repeating event, so
   declare record/event/form context and an explicitly false
   `has_repeating_event_context`, rather than treating the repeating form as
   an event alternative. It has no participant/public-survey fallback. Its
   Survey Queue and Participant List callers differ in user and PAGE state, so
   leave those properties absent.
   When multiple editable source surfaces converge in the same runtime
   replacement call, give them one source kind. Alerts & Notifications email
   subject, message, and SendGrid template-data values all reach
   `Alerts::sendNotification()`, which passes the alert record and event to
   Piping. Conditional-logic-only alerts may have no instrument, and cron
   delivery has no authenticated user, so declare only record/event plus the
   absence of participant/public-survey fallbacks; leave form, user, repeat,
   and page context unclaimed.
3. Confirm the catalog is transported by
   `buildAuthoringSyntaxEditorCatalog()`. The workspace starts loading it when
   opening and applies it as soon as it arrives, so completion,
   Piping/reference help, browser diagnostics, and the
   `diagnoseAuthoringSyntax()` server fallback receive the same source kind.
4. Add PHP/browser fixtures for a project-field reference, a smart variable
   requiring each unavailable context, and a context-independent smart
   variable in the restricted source, plus an unknown future source kind. For
   a recipient-dependent policy, cover all-guaranteed, mixed/partial, and
   fully-unavailable selections; assert completion remains available but
   labelled for partial context, and is suppressed only when unavailable. For
   a public-survey route, cover the supported public form and a different known
   survey form. The resulting context findings must be warning-only unless a
   runtime validator itself rejects the syntax.

## Add or change Field Embedding support

1. Trace both `Piping::replaceEmbedVariablesInLabel()` and
   `doFieldEmbedding()` before changing authoring behavior. Keep the pure
   parser limited to the runtime curly-brace grammar; do not encode field,
   host, or page policy in it.
2. Enable a host only when that exact stored value is passed to the runtime
   replacement method. Give its workspace policy the metadata attribute it
   replaces (`element_label`, `element_note`, `element_preceding_header`, or
   `element_enum`). A per-choice editor must additionally pass the choice
   code; replacing one choice does not replace another choice's embedding.
3. Extend the catalog builder only with runtime-proven metadata: valid project
   fields, the record ID, stored embedding occurrences, direct embedded-field
   dependencies, and survey-page placement when the form has question-by-
   section pagination. It must use the selected active/draft metadata scope.
   Each editor invocation must name its actual persisted host field and
   metadata attribute. Do not assume a reused UI input has one identity: the
   Online Designer's Field Label input edits a stand-alone section header as
   `element_preceding_header` on `sq_id`, because that mode clears
   `field_name`. The current source is the only stored occurrence that may be
   excluded from the one-use check. Invalidate or refresh the browser catalog
   after every metadata persistence path, including incremental row rendering;
   all later editor instances must receive the new page layout and occurrence
   inventory. For Field Embedding, also obtain fresh catalog metadata when an
   editor opens so a missed UI refresh cannot silently weaken availability
   diagnostics; prevent the browser from satisfying that metadata request from
   its HTTP cache. When combining languages in an HTML source, suppress Field
   Embedding semantics only for its own malformed curly-brace candidates; do
   not let an unrelated Piping parse error in another text node hide a valid
   embedding violation.
4. Mirror every new rule in `FieldEmbeddingSemanticAnalyzer` PHP and browser
   code, in field completion, and in the server fallback. HTML-capable sources
   must scan ordinary text nodes only; attributes, comments, raw script/style
   content, and syntax split by markup are not field-embedding hosts.
5. Add PHP/browser fixtures for valid same-page use and for each proven
   rejection: unknown field, record ID, other form, other survey page,
   self/nested embedding, a duplicate in the current source, and an existing
   use elsewhere. Include a same-host Label/Section Header case, a Choice
   Label case proving that a different choice still reserves the field, and a
   paginated-survey cross-section case. Run the field-embedding
   parser/semantic tests and the authoring-workspace test before handoff.

## Add or change a Piping system capability

1. Establish the replacement behavior first. Verify that the runtime path
   actually reads a named installation-wide setting, license/service predicate,
   current-project feature gate, or a proven composite of them, and determine
   its enabled/disabled result. A variable being absent from
   `getSpecialTagsInfo()` or hidden from a help page is not, by itself,
   evidence that replacement rejects the syntax or resolves it to empty text.
2. Add a stable capability name and author-facing label to
   `PipingSmartVariableCatalog`, then attach
   `required_system_capabilities` only to the Smart Variables whose replacement
   is proven to depend on it. Keep source-context requirements in
   `PipingSourcePolicyCatalog`; a runtime availability capability and a
   per-authoring-source context answer different questions.
3. Have `buildAuthoringSyntaxEditorCatalog()` transport only the minimal
   capability state needed by the editor, normally its `enabled` boolean,
   catalog label, and `availability_scope` (`system` or `project`). An enabled
   boolean may represent an inseparable set of runtime prerequisites, such as
   a global feature setting plus an active license or a current-project feature
   gate; document that composition beside the catalog entry. Never expose an
   unrelated configuration value, a
   setting's contents, or an installation-wide list merely for completion. If
   a legacy runtime registry hides the variable while its replacement remains
   structurally valid, preserve or inject a documented authoring entry so it
   stays recognized rather than becoming an unknown variable.
4. Mirror the catalog contract in the PHP and browser semantic analyzers. A
   known disabled capability should be an advisory finding that explains the
   runtime result, and completion should keep the variable visible but muted.
   Missing capability state must remain non-diagnostic for stale catalogs.
   Do not change structural parsing, Piping replacement, or runtime validation
   merely to enforce an authoring warning.
5. Test enabled, disabled, and absent-state behavior in the runtime owner and
   in PHP/browser semantics and completion. Include any controller catalog
   transport test available for the seam. Update the help/reference behavior
   and this manual when a new capability representation or transport rule is
   introduced.

## Add a Special Function

1. Implement and secure the runtime behavior first. Add its public name to
   `LogicParser::$allowedFunctions` only with the corresponding parser
   translation/evaluation implementation and runtime tests. Internal helpers
   (for example `*RC` implementation functions) are not automatically public
   functions.
2. Add one public `function(...)` definition to `LogicFunctionCatalog`, with
   a category, signature, parameters, completion snippet, author-facing title
   and description, and `return_types`. Parameter type declarations feed
   diagnostics; use `mixed` when no sound static constraint exists.
3. Keep the catalog public-only. `Design::renderSpecialFunctionInstructions()`
   and the authoring workspace consume it, so this one change must make the
   reference, hover/completion, browser diagnostics, and PHP fallback agree.
4. Extend `LogicFunctionCatalogTest` for the entry and add shared semantic
   fixtures for its valid use, arity boundaries, and every directly inferable
   type error. Verify nested calls where the declared return type matters.
5. If the function requires grammar beyond the existing Logic syntax, change
   the PHP and browser syntax parsers together and add equivalent syntax
   fixtures. Never add a catalog entry for text the runtime cannot accept.

## Add a Built-in Action Tag

1. Implement the tag's runtime behavior in every intended form, survey,
   export, mobile, or other consumer. Register its public name and description
   in `Form::getActionTags()`, with the same feature/configuration guards used
   by the runtime.
2. Decide explicitly whether it belongs in Online Designer preview:
   `Form::getActionTags(true)` is intentionally a narrower list. Update
   preview/rendering code and the `getActionTagMatchRegex*()` behavior when
   the tag needs to be recognized there.
3. Ensure its parameter form, nesting, and `@IF` interaction are representable
   by the shared PHP `ActionTagParser` and browser
   `ActionTagSyntaxParser`. Add matching fixture cases for valid, incomplete,
   malformed, and deactivated forms as applicable. Parser recognition alone
   must not claim the tag is valid for every field type or runtime surface.
4. Confirm the project catalog includes the tag (it is derived from
   `Form::getActionTags()`), that completion/hover describe it, and that
   `Design/action_tag_explain.php` presents correct help. The matching
   PHP/browser registration analyzer fixtures must recognize the new name;
   an unknown-name finding is advisory only. Include a feature-specific runtime
   test for field-type/context restrictions.
5. Add an `ActionTagCatalog` property only after tracing the precise runtime
   consumer. A required parenthesized expression, quoted assignment, explicit
   no-parameter form, target field type, validation prefix, named project
   capability, or allowed
   field-type/validation combination must be represented per tag and verified
   in matching PHP/browser semantic fixtures. Retain any runtime-observable
   distinction such as a
   whitespace-only quoted `@DEFAULT` value. Treat compatibility with a legacy
   form that saves but does not calculate as an advisory warning. For a
   runtime-compatible legacy synonym, give the old name the same proven
   properties and a name-only `deprecated_replacement` advisory; preserve any
   runtime collision precedence when both names appear. Do not copy a built-in
   property onto a module tag without manifest metadata that proves it.
   When a tag's runtime consumes an embedded Logic expression, use the shared
   Logic parser/analyzer only for the exact enabled source span the runtime
   consumes, preserve its source offsets, and keep the result advisory rather
   than evaluating it in the editor.
6. For an External Module tag, update the module manifest and module tests
   instead. Confirm collision behavior with a built-in tag and its appearance
   in the project-specific help/catalog; do not edit `Form::getActionTags()`.

## Required parity and regression checks

For every addition, run the smallest relevant tests while developing and the
combined suite before handoff:

```bash
cd /home/gr/redcap/codebase
php UnitTests/vendor/bin/phpunit UnitTests/AuthoringSyntax/Catalog/LogicFunctionCatalogTest.php
php UnitTests/vendor/bin/phpunit UnitTests/AuthoringSyntax/Catalog/LogicSmartVariableCatalogTest.php
php UnitTests/vendor/bin/phpunit UnitTests/AuthoringSyntax/Logic/LogicSemanticAnalyzerTest.php
node UnitTests/AuthoringSyntax/Logic/LogicSemanticAnalyzerJsTest.js
php UnitTests/vendor/bin/phpunit UnitTests/ActionTags/ActionTagParserTest.php
node UnitTests/ActionTags/ActionTagSyntaxParserJsTest.js
php UnitTests/vendor/bin/phpunit UnitTests/AuthoringSyntax/Catalog/ActionTagCatalogTest.php
php UnitTests/vendor/bin/phpunit UnitTests/AuthoringSyntax/ActionTag/ActionTagSemanticAnalyzerTest.php
node UnitTests/AuthoringSyntax/ActionTag/ActionTagSemanticAnalyzerJsTest.js
node UnitTests/AuthoringSyntax/Logic/LogicAuthoringWorkspaceSemanticTest.js
php UnitTests/vendor/bin/phpunit UnitTests/AuthoringSyntax/Piping/PipingSyntaxParserTest.php
node UnitTests/AuthoringSyntax/Piping/PipingSyntaxParserJsTest.js
php UnitTests/vendor/bin/phpunit UnitTests/AuthoringSyntax/Catalog/PipingSmartVariableCatalogTest.php
php UnitTests/vendor/bin/phpunit UnitTests/AuthoringSyntax/Catalog/PipingFieldParameterCatalogTest.php
php UnitTests/vendor/bin/phpunit UnitTests/AuthoringSyntax/Catalog/PipingSourcePolicyCatalogTest.php
php UnitTests/vendor/bin/phpunit UnitTests/AuthoringSyntax/Piping/PipingSemanticAnalyzerTest.php
node UnitTests/AuthoringSyntax/Piping/PipingSemanticAnalyzerJsTest.js
node UnitTests/AuthoringSyntax/Piping/PipingAuthoringWorkspaceTest.js
git diff --check
```

Run only tests relevant to the feature while iterating, but do not omit a
paired PHP/browser test when a shared parser, semantic analyzer, or catalog
changes. Add a test in the runtime suite that owns the new behavior; the
authoring tests do not evaluate production behavior.

## Before requesting review or opening the PR

- [ ] The public contract and source/context availability are documented.
- [ ] Runtime registration/evaluation and feature-specific tests are present.
- [ ] PHP and browser structural/semantic behavior agree, including failure
  cases.
- [ ] Catalog entries have complete user-facing text and sound type metadata.
- [ ] Completion, hover, and reference/help are correct for supported sources.
- [ ] External Module, permissions, project setting, draft, longitudinal, and
  repeating-context implications have been considered where applicable.
- [ ] This manual and the affected implementation/rollout documentation were
  updated if the maintenance surface changed.
- [ ] The PR description identifies the authoritative runtime owner and the
  tests proving behavior; it does not describe editor completion as runtime
  support.

The final two boxes are mandatory for this workstream's PR.
