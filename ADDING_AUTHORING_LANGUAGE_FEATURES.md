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
| Built-in Action Tag | `Classes/Form.php`: `Form::getActionTags()` plus every runtime consumer that implements the tag | `Design/action_tag_explain.php`, the `action_tags` catalog, and the advisory `ActionTagSemanticAnalyzer` registration check | `Controllers/DesignController.php`: `action_tags` catalog | `ActionTagParser` and `ActionTagSemanticAnalyzer` PHP/JS fixtures; feature-specific runtime tests; Online Designer applicability |

External Module action tags are a separate case. Their manifests feed
`ExternalModules::getActionTags()` and are exposed by the project catalog and
Action Tags help automatically. The core must not add an EM-specific runtime
implementation or treat a module tag as a built-in tag.

The current Action Tag semantic scope is deliberately only a project
registration advisory. `ActionTagSemanticAnalyzer` compares structurally valid,
enabled tags with the shared `action_tags` catalog, which merges
`Form::getActionTags()` and tags from External Modules enabled for that project.
It warns when a name is absent, without blocking the annotation or attempting
to interpret parameters, field-type restrictions, or module-specific runtime
semantics. A missing catalog must remain structural-only for stale clients;
an explicitly empty catalog means no names are registered. Disabled tags do
not warn because they do not apply at runtime.

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

External Module-provided Action Tags will need a documented `config.json`
schema for their authoring syntax requirements: at minimum, the tag's syntax
and parameters, and eventually any machine-readable context or field-type
constraints that the enhanced tools can safely diagnose. The EM framework must
validate and expose that metadata through `ExternalModules::getActionTags()`.
Only then can the shared parser, completion, hover, and diagnostics provide
meaningful structured support beyond the current name-and-description entries.
Until then, an EM tag remains structurally recognized and documented, but its
module-specific syntax and semantics remain the module's responsibility.

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
   broad Smart Variable allow-list.
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
5. For an External Module tag, update the module manifest and module tests
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
