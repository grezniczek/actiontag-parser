# REDCap Core Action Tag Parser Integration Plan

## Status

This document began as the action-tag-parser core-move plan. The work has
since become the **Authoring Syntax Diagnostics** initiative: the
`authoring-syntax-diagnostics` REDCap branch contains the pure action-tag
parser, shared syntax primitives, logic and piping parsers, a public logic
catalog, browser parser mirrors, and the first Online Designer workspace.

The External Module remains the reference and experimentation environment. It
contains the portable parser, condition-resolution helpers, scoped metadata
facades, and an interactive benchmark. Core integration is additive: it does
not yet replace legacy runtime consumers or make the parser a documented
developer API.

The executable action-tag contract in core is
`UnitTests/ActionTags/ActionTagParserContract.md` and its shared PHP/JS
fixtures. This plan records architecture, ownership, and the remaining
integration decisions; the companion [parser requirements and implementation document](PARSER_REQUIREMENTS_AND_IMPLEMENTATION.md) preserves the original design rationale and the outstanding acceptance work.

### Documentation maintenance

This plan and its companion requirements document are living records. Update
them as implementation progresses and whenever a design or compatibility
decision is refined or revised. Update the executable contract, shared
fixtures, and any developer or user documentation affected by the same change
when that is warranted.

### Current authoring integration

- Field Annotation, Quick-modify fields' custom Action Tags source,
  calculations, and branching logic use the reusable Online Designer authoring
  workspace. The Quick-modify source is a readonly control that opens the
  Action Tags workspace on focus or click and identifies itself with
  `quick_edit.action_tags`. Syntax feedback is diagnostic only; the established
  server-side validators remain authoritative when metadata is saved.
- The logic and action-tag browser parsers are mirrors for responsive editor
  feedback. They must remain behaviorally aligned with their PHP counterparts,
  even though UI offsets use UTF-16 and server offsets use UTF-8 bytes.
- SQL fields are not REDCap logic. Their workspace uses ACE's bundled MySQL
  mode (appropriate for REDCap's MySQL/MariaDB support), extended to highlight
  bracketed SQL-field smart variables and data-table placeholders. It exposes
  Edit and Help only: manual Ctrl+Space completion supplies smart variables
  and project field names in quoted SQL values. SQL does not receive parsing,
  validation, SQL-language completion, summary, or structural analysis.
- Piping has PHP and browser parser mirrors validated by shared fixtures, and
  matching `PipingSemanticAnalyzer` products for metadata-aware diagnostics.
  After a structurally valid reference, the analyzer checks project fields and
  smart variables, named events, event/form designation, checkbox targets,
  and checkbox choice codes. Built-in smart-variable entries also receive
  catalog-owned `supports_event_qualifier` and
  `supports_instance_qualifier` properties from `PipingSmartVariableCatalog`.
  An explicitly unsupported qualifier yields a warning only, because runtime
  replacement still accepts the structural form; an absent property remains
  non-diagnostic for stale payloads and future module-provided variables.
  `PipingSmartVariableCatalog` also declares evidence-backed instrument,
  enumerated, and free-text parameter contracts for the URL and survey smart
  variables. The analyzer validates a known instrument and survey status,
  warning rather than blocking if an unknown instrument might fall back to the
  current form. With a named event qualifier, a known instrument outside the
  event's designated forms is likewise an advisory warning; completion keeps
  it available but muted. Completion supplies matching project instruments and
  duration units. Explicit `requires_record_context`,
  `requires_event_context`, `requires_form_context`, and
  `requires_record_or_public_survey_context` metadata identifies only Smart
  Variables whose runtime replacement consumes the corresponding context.
  Other Piping parameter semantics and broader source availability remain
  runtime behavior until their contracts are cataloged.
  `PipingFieldParameterCatalog` similarly owns the evidence-backed project-field
  modifier contract. Each catalog field carries its applicable
  `piping_parameters` for completion, and the top-level known modifier set lets
  the PHP/browser analyzers issue warning-only diagnostics for known modifiers
  used on an incompatible field. Its `exclusive_group` metadata also prevents
  conflicting known modifiers from being suggested together and warns if they
  are manually combined. Unknown modifiers remain runtime-compatible.
  `PipingSourcePolicyCatalog` separately records the evidence-backed sources
  that have no record context. It drives the workspace's field-completion
  availability and both semantic analyzers: project-field references in those
  sources produce a warning, while only Smart Variables that explicitly
  require an unavailable record, event, form, or public-survey route are muted
  in completion and warned. Twilio's manual invitation path provides the first
project event and the public route for the project's `firstForm`, but no record or form;
  Project Dashboard rendering provides none of those contexts. The Twilio
  policy transports its project-specific public survey form, so completion and
  warnings reject another known survey. Unknown source kinds retain existing
  behavior.
  The default Survey Queue custom-text renderer supplies a record and the
  project first event but no form, so only form-dependent Smart Variables are
  muted and warned; its language-specific translation path is intentionally
  outside that default-source policy.
  A source policy may also limit Piping itself to explicit
  `piping_delivery_types`. Twilio supplies the live delivery selection to both
  browser and server semantic contexts: only `SMS_INVITE_WEB` invokes Piping;
  other methods receive a warning and muted/suppressed completion because the
  message would contain literal brackets. Absent delivery selection preserves
  compatibility.
  The Edit Field dialog's
  one-line `#field_note` uses the authoring workspace with piping diagnostics, Summary and Structural
  analysis tabs, reference hover documentation, and manual Ctrl+Space
  completion for fields, smart variables, and (in longitudinal projects)
  named events. Selecting an event produces only `[event_name]`; when the
  next bracket begins, event-designated fields rank first and fields outside
  that event's designated forms remain available but muted. Smart variables
  with explicit event-qualifier support are ranked alongside that context;
  variables that explicitly ignore it remain available but muted. After a
  completed smart variable, named instance qualifiers follow the same catalog
  rule: supported choices are active and explicitly unsupported choices muted.
  The Piping
  completion popup is widened from ACE's 300px default to 450px for readable
  field labels without changing the other authoring modes. The field itself
  retains its normal direct-edit behavior; the adjacent pencil button, F2, and
  double-click open the workspace. It warns
  before an update if the workspace contains line breaks; saving collapses
  each line-break run and immediately adjoining whitespace to one space,
  matching the input surface without changing runtime piping. The workspace
  recognizes field and smart-variable colon parameters (for example
  `[field:value]`), diagnosing only catalog-known project-field modifiers that
  do not apply to the target field, alongside checkbox, event, and
  repeating-instance forms; its Piping help button opens the standard REDCap
  piping explanation. Manual Ctrl+Space completion also suggests applicable
  field parameters from field type and validation metadata; it deliberately
  does not guess smart-variable parameter values such as instrument names,
  free text, or report identifiers. Piping is not a runtime replacement.
- Authoring-workspace invocations support a stable logical `ref` identifier
  (for example, `field.note`, `field.sql`, `survey.instructions`, or
  `survey.confirmation_email_subject`). All current built-in workspace
  invocations pass a `ref`. An exact-ref source-policy registry owns each
  source's static syntax, presentation, one-line behavior, HTML mode, and
  field-embedding permission; callers provide only dynamic integration details
  such as a save callback, focus target, or host form. The `ref` remains UI
  context rather than parser input or a runtime behavior change. Future
  context-specific policies, such as permitting a new smart variable in only
  selected survey settings, must extend this registry rather than derive
  meaning from a DOM selector or duplicate flags at individual call sites.
- Field embedding has matching pure PHP and browser parser mirrors, validated
  by shared fixtures. It recognizes the existing runtime grammar
  `{field_name}` and `{field_name:icons}` only; the browser parser is combined
  with piping in the Field Label and Field Note source editors through their
  source-policy field-embedding permission. It highlights valid and malformed
  candidates, provides field hover information and manual Ctrl+Space field
  completion after `{` limited to fields on the host instrument, and suggests
  the supported `:icons` option after a completed same-form field. It exposes
  the standard Field Embedding help dialog.
  As with piping, recognition is limited to ordinary HTML text nodes, so a
  candidate in markup or split across markup is deliberately unrecognized.
  This is syntax feedback only: it does not determine whether the named field
  exists, is the record ID, is on the host instrument/page, is self/nested, or
  otherwise satisfies runtime field-embedding rules.

### Deferred authoring UI/UX refinements

- Revise the workspace expand/collapse control in `rcDialog` so its icon and
  behavior do not depend on jQuery UI.
- Add SQL-specific help based on `Design/sql_field_explanation.php`, either as
  a dedicated workspace tab or through an adjacent help button. Decide the
  presentation before duplicating the explanatory content.
- When the Dynamic Query Tool is enabled, expose its link beside the existing
  authoring-workspace reference/explanation buttons, consistent with the Edit
  Field dialog.
- The Field Label row now has an explicit **Edit with authoring editor**
  action. It opens the source in ACE for piping and field-embedding
  diagnostics, highlighting, and completion. A reusable TinyMCE handoff
  keeps a live TinyMCE editor synchronized through a detached source buffer,
  so its backing textarea stays hidden and cannot diverge. The Field Label and
  Field Note source policies enable `rich_text` HTML mode, which enables ACE
  HTML highlighting and field embedding where it is supported.
- In an HTML-capable piping workspace (`rich_text` or the restricted
  `filter_tags` mode), diagnostics, highlights, hover help,
  and completion operate only in ordinary HTML text nodes. Tags, attributes,
  comments, and script/style content are excluded, and a reference split by
  markup is not recognized as valid piping.
- HTML-capable piping workspaces run ACE's bundled HTML worker alongside the
  piping and field-embedding parsers. Their annotations are merged, so basic
  markup diagnostics do not overwrite authoring-syntax findings (and vice
  versa). The matching ACE v1.44.0 `worker-html.js` is bundled with the local
  ACE modes and is required for this worker path.
- Survey Settings now provides the same explicit action beside its existing
  Piping help links for Offline Instructions, Survey Instructions, the
  acknowledgement, stop-action acknowledgement, and confirmation-email body.
  Those sources remain TinyMCE-based visual editors; ACE is the deliberate
  HTML-aware source-editor path. The confirmation-email subject and end-survey
  redirect URL are one-line readonly source controls that open the piping
  workspace on focus or click. The auto-continue condition uses the logic
  workspace in the same way. These Survey Settings sources are piping-only:
  field embedding remains disabled there.
- Automated Survey Invitations use the same source-policy integration for
  their email subject, rich-text email content, and send-condition logic. The
  subject is a readonly one-line piping source; the existing Piping help area
  provides an explicit ACE source-editor action for the TinyMCE email body;
  and the condition is a readonly logic workspace that retains the existing
  trimming, activation, and server-side validation behavior after save. Field
  embedding remains disabled for all ASI sources.
- Survey Queue's condition logic uses the same readonly logic workspace. On
  save it retains the existing trimming, automatic condition activation, and
  validation behavior. Its rich-text custom text has an explicit TinyMCE
  source-editor action using the `survey_queue.custom_text` piping policy.
  Field embedding remains disabled.
- Bulk survey invitations and the follow-up-survey popup now cover their
  one-line subjects and rich-text message bodies with exact invitation-source
  policies. The body actions retain TinyMCE as the visual editor and run the
  established survey-link check after an authoring-workspace save.
- Form Display Logic conditions use the readonly logic workspace both for the
  initial condition and controls added by the dialog's repeater. Saving keeps
  the existing client-side validation and returns focus to that condition's
  target-form selector. Field embedding remains disabled.
- Record Status Dashboard filter logic uses a readonly logic workspace. Its
  existing trimming and validation behavior is retained on save, and focus
  returns to the dashboard Save button. Field embedding remains disabled.
- Data Export report-builder advanced logic uses a readonly logic workspace.
  Saving invokes the existing report-specific validation and returns focus to
  the option to switch back to simple logic. Field embedding remains disabled.
- PDF Snapshot trigger logic uses a readonly logic workspace in its Add/Edit
  Trigger dialog. Saving retains the existing trim, trigger activation, and
  client-side validation behavior, and focus returns to the survey-completion
  trigger selector. Field embedding remains disabled.
- Randomization real-time trigger logic uses a readonly logic workspace. Its
  established AJAX save path, including server-side trimming, is unchanged;
  opening the workspace retains the existing behavior of enabling the Save
  button, and focus returns to the trigger-mode selector. Field embedding
  remains disabled.
- MyCap's participant-allow condition uses a readonly logic workspace in its
  configuration dialog. Saving retains its existing client-side logic
  validation before the dialog's established AJAX save and reload flow; focus
  returns to the dialog's close control. Its custom participant label is also
  a readonly, one-line piping source in that dialog. Field embedding remains
  disabled.
- Alerts & Notifications uses the same source-policy integration for its
  trigger logic, email subject, rich-text alert message, and the values of new
  SendGrid dynamic-template data items. The condition, subject, and template
  values are readonly source controls that open the workspace on focus or
  click. The rich-text message retains TinyMCE as its visual editor and gains
  an explicit HTML-aware source-editor action beside the existing Piping help.
  Field embedding remains disabled for all Alert sources. Email-address and
  phone-number controls remain outside this integration because their
  recipient/format validation has different authoring semantics.
- Data Quality rule logic uses a readonly logic workspace for both the
  new-rule input and inline editing of existing user-defined rules. The
  existing rule-specific validation and Save behavior are retained after an
  authoring-editor save, and field embedding remains disabled.
- Project Setup's Custom Record Label, Define My Events' Custom Event Label,
  and the Custom Label inputs in the Repeating Instruments and Events dialog
  now use readonly, one-line piping workspaces on focus or click. They carry
  the logical references `project.custom_record_label`,
  `event.custom_event_label`, and `repeat_instrument.custom_label`. Their
  `filter_tags` HTML mode enables syntax-aware HTML highlighting but not field
  embedding. On opening, every ordinary stored `<br>`, `<br >`, `<br/>`, or
  `<br />` spelling is shown as an editor line break; a one-line warning lets
  the author save edited line breaks as spaces or canonical `<br>` tags and
  preselects the latter when the stored source already contains a break tag.
  Saving removes trailing horizontal whitespace; Cancel leaves the source
  byte-for-byte unchanged. This completes the
  immediate data-entry-form design label surfaces while retaining each
  screen's established save flow.
- Project Setup's Data Entry Trigger URL is a one-line piping workspace that
  preserves the original URL/localhost checks after save. e-Consent's custom
  label is likewise a record-aware one-line piping workspace in its existing
  AJAX dialog and retains its normal save path.
- Project Dashboard body text has an explicit TinyMCE source-editor action.
  It is HTML-aware for text-node syntax feedback and deliberately offers smart
  variables, not record fields: dashboard smart charts, tables, and functions
  remain dashboard-specific runtime syntax. The real-time Twilio SMS message
  uses the same smart-variable-only completion scope because it has no record
  context. Both retain their existing send/save actions.
- Generic External Modules JSON settings and the Vue `useLogicTextArea`
  helper remain on their legacy editor path. They are framework hooks rather
  than concrete source contracts, so assigning them a single workspace policy
  would incorrectly assert a common runtime grammar.
- Extend field-embedding-aware source editing only to the remaining
  runtime-supported host surfaces (Section Header and Choice Label) as their
  explicit authoring actions are introduced. Do not enable it for generic
  piping-only surfaces such as survey instructions or exit text.
- Add a later metadata-aware field-embedding diagnostic layer for record-ID,
  self/nested, form, and survey-page rules. It must remain separate from the
  pure parser and must use current draft-aware project metadata.

## Core Objective

Expose a documented developer method for action-tag parsing while preserving current core behavior until each consumer can safely migrate. The same parser must be usable for native REDCap tags and External Module tags, but the parser itself must not depend on a particular tag catalog or project context.

## Proposed Core Architecture

| Component | Core responsibility |
| --- | --- |
| `Classes/ActionTagParser.php` | New, pure parser class moved from the EM implementation. It has no REDCap runtime, database, project, user, `Form`, `Piping`, or External Module dependency. |
| `Classes/REDCap.php` | Documented developer-facing facade, initially `REDCap::parseActionTags(string $annotation, array $options = []): array`. It delegates to the parser. |
| `Classes/ActionTags.php` | Existing compatibility utility. It remains in place initially and may later delegate selected simple operations after compatibility tests. |
| `Classes/Form.php` | Existing legacy extraction and `@IF` runtime-evaluation helpers. These are migration candidates, not parser dependencies or initial replacement targets. |
| `Classes/ActionTagIndex.php` | Framework-neutral aggregation of caller-supplied annotations into field, tag, instrument, and per-field condition views. Metadata retrieval remains with a core or EM-framework facade. |
| `Classes/ActionTagConditionResolver.php` | Runtime helper that evaluates a parse result's opaque condition definitions once in a supplied context and applies its ordered references to tags. Its `resolveMany()` variant memoizes identical conditions across caller-supplied results. It remains separate from the pure parser. |
| `Classes/ActionTagProjectConditionResolver.php` | REDCap-aware companion for bulk runtime resolution. It preloads the union of condition fields for one record/context and delegates structural condition application to `ActionTagConditionResolver`. It is not part of the pure parser. |
| `Classes/ActionTagFieldsParser.php` | Scope-selecting REDCap metadata facade over the pure parser. It parses an arbitrary field list, one instrument, or an entire project without adding runtime evaluation to the parser. |
| `Classes/ActionTagFieldsConditionResolver.php` | Scope-selecting REDCap facade over the batch resolver. It resolves an arbitrary field list, one instrument, or an entire project, allowing pages and surveys to batch only the fields they need. |
| Future `ActionTagValidator` (name provisional) | Semantic validation against tag definitions, parameter schemas, metadata, enabled modules, and field/context rules. It remains separate from the parser. |

`ActionTagParser` is the recommended core class name: it is direct, discoverable beside `ActionTags`, and clearly distinct from a future semantic validator.

## Developer API and Ownership

`ActionTagParser::parse()` is currently an internal core implementation
surface. Internal callers can use it, but its signature and result contract are
not yet guaranteed to third-party developers.

When the contract is ready for public support, core should add, rather than
replace, this documented facade:

```php
REDCap::parseActionTags(string $annotation, array $options = []): array
```

The method should expose the parser's documented fast and diagnostic modes without adding project-specific semantics. Its PHPDoc must link to the stable result contract and state that syntax recognition does not establish whether a tag is known or valid for a project.

The `REDCap` facade is a stability and documentation boundary, not a technical
prerequisite for using the internal class. Core owns both the parser and the
eventual stable facade. The EM remains the reference implementation and the
place to develop the first semantic Field Annotation checker. A subsequent
semantic-validation initiative may provide shared schemas/registration, but
must not be required before the parser API is released.

## `@IF` Integration Decision

`@IF` is a structural parser exception: core should expose the parser's recursive container representation and effective conditionals, but it must not change runtime evaluation as part of this work.

- The parser reports opaque condition text and the action tags structurally contained in `@IF` branches.
- It does not evaluate or logic-validate conditions.
- `Form::replaceIfActionTag()` remains the authoritative runtime evaluator during the migration.
- In diagnostic integration, `@IF` containers are retained for editor feedback; fast parser consumers receive the flattened conditional tag view specified in the parser requirements.

This gives callers accurate annotation structure without coupling the new parser to records, piping, project logic, or legacy evaluation behavior.

`ActionTagConditionResolver` is a future API/EM-framework companion rather than parser logic: it receives already parsed results and an explicit runtime context (or evaluator callback), evaluates each result-local condition once, and marks which flattened tags are active. For project-wide callers, `ActionTagProjectConditionResolver` batches field retrieval for the union of conditions and reuses values for identical condition text. `ActionTagIndex` similarly accepts annotations supplied by a caller and creates aggregate field/tag/instrument views without knowing how metadata was obtained. These helpers can move to core or the EM Framework independently of the parser class.

## Future Runtime Performance Architecture

This is a future runtime-integration design, not a parser requirement. The pure parser stays stateless and cache-free.

```text
Project metadata revision
        │
        ▼
ActionTagPlan cache
(field → parsed tags, conditions, dependency/index views)
        │
        ▼
RuntimeActionTagContext
(record, event, instrument, instance, already-loaded record data)
        │
        ▼
Request-local resolved-plan cache
(field → active tags and requested tag views)
```

`ActionTagPlan` is the proposed immutable, project-metadata-derived representation. It may hold fast parser output plus field/tag/instrument indexes and condition dependencies. It is safe to cache only while the underlying project metadata revision is unchanged; it contains no record-specific result.

`RuntimeActionTagContext` is the proposed explicit holder for record/event/instrument/instance context and record data already fetched by the page renderer. It owns request-local condition-result memoization. Its cache key must include all runtime inputs that can affect logic, such as record, event, repeat context, user-sensitive piping/smart variables, and unsaved data state. Runtime results must not be reused blindly across requests.

Two runtime strategies should remain explicit:

- **Active-only** follows selected `@IF` branches and evaluates only reachable conditions. It is the intended production path for rendering forms and surveys.
- **All-states** evaluates conditions needed to report every flattened tag as active or inactive. It is intended for diagnostics, Explain-style tooling, and inspection APIs.

### Environment Policy

Development projects do not need persistent action-tag-plan caching. Their runtime integration should use scoped batching: parse and resolve only the current instrument's relevant annotations, fetch the union of needed values once, and share a request-local `RuntimeActionTagContext` across consumers.

`ActionTagFieldsParser` and `ActionTagFieldsConditionResolver` are the initial EM-facing shape for that scoped work. Both accept the same caller-selected field list and provide `Instrument` and `Project` convenience scopes. Their final `tryDraftMode` argument follows REDCap's usual metadata-helper convention; it uses `metadata_temp`/`forms_temp` only for production projects in draft mode. Draft Preview also selects draft metadata automatically when it applies to the current project. The resolver consumes the parser helper's output, so a paged survey can parse or resolve only its page fields, a field-level consumer can pass one field, and inspection tooling can deliberately request the whole project.

For production projects, an `ActionTagPlan` cache may be enabled and refreshed as part of REDCap's existing **Apply production changes** routine. That gives a natural metadata-consistency boundary and avoids adding ad-hoc invalidation paths during active development. A production refresh should rebuild the plan/index for the released metadata revision; a subsequent request can then use active-only, instrument-scoped resolution with already-loaded page data.

Before core adoption, benchmark output should distinguish parser-cache hits, annotations/conditions considered, reachable versus total conditions, preloaded fields, record-data query count, and logic-evaluation count. This prevents parser and runtime/database costs from being conflated.

## Compatibility and Migration Strategy

Core already has overlapping legacy parsing behavior in `Classes/ActionTags.php` and `Classes/Form.php`. The Online Designer also uses legacy matching helpers to classify and highlight action tags. The initial parser addition must not alter those code paths.

The migration rules are:

1. Add the parser and `REDCap` facade additively; preserve existing public helpers unchanged.
2. Port the EM parser fixture suite to core before any core consumer is migrated.
3. Classify fixture differences from legacy helpers as either required compatibility or an intentional, documented correction.
4. Begin with inspection-oriented consumers, such as annotation checking or editor highlighting, where diagnostic output adds value and does not change runtime behavior.
5. Consider delegation from simple `ActionTags` utilities only after dedicated compatibility coverage.
6. Migrate `Form` helpers and runtime callers one consumer family at a time, each with regression coverage and a clear semantic owner.

The existing core autoloader already resolves classes from `Classes/`, so adding `Classes/ActionTagParser.php` should require no loader work.

## Semantic Validation and External Modules

Parser output is structural and applies equally to built-in and custom tags. Semantic validation remains a separate layer.

Native tag descriptions are currently available through facilities such as `Form::getActionTags()` and `ExternalModules::getActionTags()`, but descriptions alone do not define parameter schemas, field constraints, or contextual rules. Custom-tag manifests likewise generally provide names/descriptions rather than machine-readable parameter schemas.

The eventual validator therefore needs an extensible definition/schema mechanism. Until that exists, the EM may provide rich module-specific feedback on top of the shared parser. The parser API should not wait for this standardization.

## Current and Future Core Touchpoints

- `/home/gr/redcap/codebase/Classes/ActionTagParser.php` — implemented pure parser class.
- `/home/gr/redcap/codebase/Classes/AuthoringSyntax/` — implemented shared
  primitives, logic/piping syntax products, and the public logic catalog.
- `/home/gr/redcap/codebase/Controllers/DesignController.php` and
  `/home/gr/redcap/codebase/Resources/js/AuthoringSyntax/` — implemented
  authoring catalog, browser diagnostics, and reusable workspace.
- `/home/gr/redcap/codebase/Classes/REDCap.php` — future documented
  developer-facing facade once the action-tag contract is approved as stable.
- `/home/gr/redcap/codebase/Classes/ActionTags.php` and
  `/home/gr/redcap/codebase/Classes/Form.php` — later, selective compatibility
  delegation and runtime migration. `Form::replaceIfActionTag()` remains the
  runtime authority until that work is separately approved.

## Core Rollout Sequence

1. **Completed:** Implement and exercise the portable parser in the EM, then
   move its contract and shared fixtures into core.
2. **Completed:** Add diagnostic authoring integration for Field Annotation,
   calculations, and branching logic without changing runtime evaluation.
3. **Completed:** Make the browser logic parser iterative/bounded, align PHP
   and JS action-tag whitespace semantics, and give SQL fields an
   SQL-highlighting-only path.
4. **Completed:** Establish a shared PHP/JS piping-syntax contract and a
   standalone browser mirror, without adding a piping authoring surface.
5. **Completed (initial corpus):** Use deliberately selected real-world
   development-project expressions and annotations to establish compatibility
   decisions. This confirmed digit-leading unique event names for piping and
   the policy that historic JavaScript calculations remain illegal for new
   authoring.
6. **Completed (first semantic scope):** Add matching PHP/browser Logic
   semantic analysis, backed by draft-aware project metadata, for unknown
   references/events/functions, event-form availability, checkbox choices,
   function arity, and directly inferable function argument types (including
   nested calls using catalog-declared result types), plus directly known
   numeric/Boolean operator operands. Typed smart-variable schemas include
   explicit source-kind availability; the current safe baseline preserves
   existing availability until source-specific behavior is reviewed. It also
   warns for a calculation, CALCTEXT, or CALCDATE field that directly references
   itself, while permitting explicit other-event or repeat-instance references.
   It remains editor-only and does not evaluate or alter runtime validation.
7. **Completed (first Piping semantic scope):** Add matching PHP/browser
   Piping semantic analysis, backed by the same project catalog, for unknown
   field/smart-variable references and events, event-form availability, and
   checkbox target/choice validation. Built-in `PipingSmartVariableCatalog`
   entries additionally declare event and instance qualifier support, which
   yields warning-only diagnostics when explicitly unsupported. Evidence-backed
   instrument and enum parameter contracts now provide conservative warnings,
   metadata checks, and completion. `PipingFieldParameterCatalog` now supplies
   the matching per-field modifier contract for completion and warning-only
   diagnostics. `PipingSourcePolicyCatalog` now provides the narrow,
   evidence-backed record/event/form/public-survey-context contract for
   restricted authoring sources.
   It intentionally leaves unknown parameters and broader source-specific
   smart-variable availability to runtime behavior until their contracts are
   cataloged.
8. **Required before PR:** Follow and update the Manual,
   [`ADDING_AUTHORING_LANGUAGE_FEATURES.md`](ADDING_AUTHORING_LANGUAGE_FEATURES.md)
   for every new Smart Variable, Piping project-field modifier, Special
   Function, or Action Tag. It records the distinct runtime, parser, catalog,
   help, completion, diagnostic, and test responsibilities so authoring
   completion is not mistaken for runtime support.
9. Use the EM benchmark across representative projects to establish parser,
   resolver, preload, and evaluation costs before considering runtime use.
10. Publish `REDCap::parseActionTags()` only when its contract, PHPDoc, and
   compatibility policy are ready to be stable for developers.
11. Evaluate selective compatibility delegation in `ActionTags`, then migrate
   `Form`/runtime consumers one family at a time with regression coverage.
12. Introduce semantic tag definitions and a validator as a distinct
   standardization project.

### Server-side calculation-cycle warnings

**Completed foundation:** `CalculationDependencyGraphBuilder` builds a pure,
conservative graph for calc, CALCTEXT, and CALCDATE fields. It resolves ordinary
event-context edges, retains repeat/dynamic/aggregate dependencies as potential
edges, and returns direct or indirect cycle witnesses.

**Implemented Online Designer pre-close flow:** before **Update & Close
Editor** closes the Calculation Editor, it sends a read-only AJAX request that
combines the active development/draft metadata with the current, still-unsaved
calculation source and target-field identity, including a newly added or
renamed field. A cycle involving that proposed field opens an rcDialog with
the concrete field names and full field/event path, labeling potential
repeat/event/record/conditional/aggregate paths. The user can **Return to
Editor** or **Save Anyway**; the latter retains the acknowledgement needed to
avoid an immediate duplicate post-save warning.

The post-save graph warning remains the fallback for saves outside the new
editor-preview flow. It never blocks or rolls back the save.

**Metadata freshness:** each Online Designer field add/edit now reloads the
affected form from the server and invalidates/refetches the shared authoring
catalog, which keeps completion and semantic diagnostics correct after field
additions, renames, and deletions.

**Implemented metadata-import flow:** the Data Dictionary upload builds graphs
for the active development/draft metadata and the validated, parsed proposed
metadata during its first review step. It shows only newly introduced cycle
witnesses as part of the existing warnings immediately above the box containing
**Commit Changes**; it is not a dialog and does not block commit. Instrument
ZIP upload and Copy Instrument instead compare the pre-import graph with a
freshly reloaded post-commit graph and retain the success dialog until the user
closes the warning. All warnings are advisory: a diagnostic failure is logged
and cannot turn a successful import into an error or rollback.

The database-backed Data Dictionary review regression test creates temporary
Development and Draft Mode projects. In each case, it retains a pre-existing
cycle in the active metadata scope and verifies that the review warning names
only cycles newly introduced by the proposed dictionary; the Draft Mode case
therefore detects an accidental comparison with production metadata.
`Calculate::getCalcFieldsByTriggerField()` remains a regex trigger-discovery
helper, not a cycle detector.

## Benchmarking and Runtime Readiness

The EM's `benchmark.php` page is an implemented interactive harness. It runs
the fast and diagnostic pure parser against the current project's annotated
fields; compares it with the legacy parser and `ActionTagHelper`; and, for a
chosen record context, reports parser, condition-discovery, record-preload,
condition-evaluation, mapping, and total resolver costs. It also records the
conditions, tags, fields, and data-query workload behind each result.

The harness is ready for representative-project benchmarking, but a broad
real-world corpus is intentionally not yet the acceptance gate: first settle
the structural and parity decisions above, then collect and classify examples
that exercise them. Runtime adoption requires stable compatibility evidence and
predictable benchmark results; it remains independent of the current
diagnostic/editor rollout.
