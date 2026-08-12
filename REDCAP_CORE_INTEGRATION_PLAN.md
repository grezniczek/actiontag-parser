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
- Every `field.*` workspace title adds its current variable name as a
  code-styled ` - [field_name]` token with muted gray brackets, including Field
  Label, Field Note, and Branching Logic. A new field without a name retains
  the base title; multi-field and non-field editors deliberately do not receive
  a synthetic field suffix.
- The logic and action-tag browser parsers are mirrors for responsive editor
  feedback. They must remain behaviorally aligned with their PHP counterparts,
  even though UI offsets use UTF-16 and server offsets use UTF-8 bytes.
- SQL fields are not REDCap logic. Their workspace uses ACE's bundled MySQL
  mode (appropriate for REDCap's MySQL/MariaDB support), extended to highlight
  bracketed SQL-field smart variables and data-table placeholders. It exposes
  Edit and Help only: manual Ctrl+Space completion supplies smart variables
  and project field names in quoted SQL values. SQL does not receive parsing,
  validation, SQL-language completion, summary, or structural analysis. When
  the author opens Help, it lazily appends the established, server-authored
  `Design/sql_field_explanation.php` payload in that tab; its content is not
  duplicated in the workspace or opened in a second dialog.
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
  project-dashboard, enumerated, and free-text parameter contracts for the
  URL and survey smart variables, plus `max_parameters` where runtime
  replacement establishes an exact upper bound. A read-only project
  `dashboards` collection completes and validates the named targets of
  `[dashboard-access-code]`, `[dashboard-url]`, and `[dashboard-link]`; an
  unknown target is an error only when that collection is current, preserving
  stale-catalog compatibility. The matching read-only `reports` collection
  completes and validates the target of `[report-access-code]`; its runtime
  requires an uppercase `R-` prefix, which is also diagnosed before the
  project lookup. Aggregate Smart Functions, Smart Charts, and Smart Tables
  also declare `supports_report_filter`: their first uppercase `R-` token
  after the field-list argument is completed and validated against that same
  collection, matching `parseSmartParams()` early return. Catalog-backed
  Stats Table output semantics use the cataloged columns plus each field's
  read-only `stats_table_supported_columns` list, both derived from
  `DataExport::getDescriptiveStats()`. This keeps browser completion and
  PHP/browser diagnostics aligned when the runtime will leave a selected
  output blank: the record ID has only `count` and `missing`, categorical
  fields also have `unique`, numeric fields have the complete set, and
  descriptive fields have none. Existing numeric-column findings are retained;
  nonnumeric cases such as `[stats-table:record_id:unique]` receive a separate
  warning-only finding. Absent field capability remains compatible with stale
  catalogs. `[data-table]` similarly has one optional canonical positive
  `project_id`. Its browser/server semantic contract rejects a malformed
  explicit value, while lifecycle validation is server-backed and receives
  only IDs requested by the expression—not an installation-wide project
  catalog or titles. Missing, deleted, and Completed target projects are
  errors; Analysis/Cleanup is a warning; Development and Production (including
  Draft Mode) are valid. `[calendar-url]` accepts no parameters, while
  `[calendar-link]` accepts one optional free-text label; extra parameters are
  advisory because runtime ignores them. Their catalog entries require the
  `calendar_feed` system capability. A disabled global Calendar Feed leaves
  those variables recognized but muted and produces an advisory that runtime
  resolves them to empty text; missing state remains stale-catalog compatible.
  The Email Verification/Unsubscribe family follows that same contract:
  `[email-verified]`, `[email-verify-url]`, `[email-verify-link]`,
  `[email-unsubscribed]`, `[email-unsubscribe-url]`, and
  `[email-unsubscribe-link]` all require a record and the composite
  `email_verify_unsubscribe` capability. Its one enabled state represents both
  the global setting and active REDCap+ features, exactly as replacement does.
  URL/status variables take no parameters; link variables take one optional
  free-text label. An unavailable feature is advisory and muted, while an
  absent capability state remains compatible with stale catalogs.
  The REDCap SHARE variables—`[redcap-share-url]`, `[redcap-share-link]`,
  `[redcap-share-ehr-list-url]`, `[redcap-share-ehr-list-link]`,
  `[redcap-share-ehr]`, and `[redcap-share-ehr-id]`—take no parameters and
  require both a record and the `redcap_share` capability. Its enabled state
  comes from `RedcapShareFeatureGate::isAvailableForProject()`, which is the
  actual system-and-project replacement guard, so its catalog
  `availability_scope` is `project`. It intentionally does not add the legacy
  reference list's REDCap+ visibility condition, because Piping's SHARE
  replacement does not use that condition. Disabled projects retain
  recognized but muted, advisory variables; missing capability state remains
  compatible with stale catalogs.
  The Rewards variables—`[reward-amount]`, `[reward-product-id]`,
  `[reward-product-name]`, `[reward-status]`, `[reward-redcap-order-id]`,
  `[reward-provider-order-id]`, `[reward-redemption-details]`,
  `[reward-redemption-link]`, `[reward-redemption-url]`,
  `[reward-redemption-code]`, `[reward-redemption-instructions]`,
  `[reward-redemption-credentials]`, `[reward-link]`, and `[reward-url]`—are
  runtime-recognized even when `Piping::getSpecialTagsInfo()` hides their
  legacy help group. The catalog injects them without a system capability,
  because that help condition is not a `pipeSpecialTags()` replacement guard.
  They require a record, use an event qualifier to select its arm, ignore an
  instance qualifier, and accept at most one explicit `R-<positive integer>`
  option parameter. Browser and server semantics warn for a malformed explicit
  ID or an extra parameter, but not for an omitted ID: Rewards-aware renderers
  may provide it implicitly or replace redemption tokens before normal Piping.
  The Access Control Group placeholders—`[user-acg-name]`,
  `[user-acg-noncompliant-rights]`, and `[acg-noncompliance-table]`—also remain
  runtime-recognized when their legacy help entries are hidden. They require a
  user context and take no parameters. Only
  `[user-acg-noncompliant-rights]` receives the `access_control_groups`
  capability: when disabled, its external helper call is intercepted and the
  variable resolves empty. The user-group name and noncompliance table use
  different unguarded runtime paths, so they remain active without an
  availability warning. Source policies and completion now use the matching
  `requires_user_context`/`has_user_context` capability alongside record,
  event, form, and public-survey context.
  `[dashboard-access-code]` likewise remains runtime-recognized when the
  global public-dashboard setting hides only its legacy help entry: its Piping
  replacement still retrieves the access code for a named current-project
  dashboard. The catalog injects it without a system capability, so it stays
  active and uses the existing one-dashboard target validation and completion.
  MyCap's project code is context-independent; its participant code, URL, and
  link require a record. The latter three use the current runtime event to
  select the participant's arm but ignore event/instance qualifiers; the link
  accepts one optional free-text label. They have no modeled MyCap-enabled
  capability: their Piping replacement does not test the project setting.
  Randomization variables require a record and ignore event qualifiers. Their
  documented inline `:n` sequence reference is an exception to the normal
  prohibition on inline instances; the timestamp variables may additionally
  use `value` for raw output. They also accept a numeric bracketed instance as
  the same reference, while named instance choices are muted and warned.
  Catalog-backed
  semantic analysis rejects a legacy final
  inline numeric or named instance for qualifier-reviewed variables and project
  fields, requiring a separate bracketed qualifier instead. The structural
  parser remains opaque for valid numeric smart-variable parameters, such as
  `[data-table:435]`. The analyzer validates a known instrument and survey
  status,
  warning rather than blocking if an unknown instrument might fall back to the
  current form. With a named event qualifier, a known instrument outside the
  event's designated forms is likewise an advisory warning; completion keeps
  it available but muted. Completion supplies matching project instruments,
  dashboard/report unique names, and duration units. Explicit `requires_record_context`,
  `requires_event_context`, `requires_form_context`,
  `requires_form_or_instrument_parameter`, `requires_user_context`, and
  `requires_record_or_public_survey_context` metadata identifies only Smart
  Variables whose runtime replacement consumes the corresponding context.
  Other Piping parameter semantics and broader source availability remain
  runtime behavior until their contracts are cataloged.
  `[instrument-name]` and `[instrument-label]` require the current form (or a
  runtime survey participant), take no parameters, and ignore event/instance
  qualifiers. `[survey-title]` likewise falls back to that form but can use a
  single known survey-instrument parameter. Thus a form-less source can
  complete `[survey-title:followup]`, while an omitted, unknown, or non-survey
  target is warned as unable to resolve there. The three variables' ignored
  event/instance qualifiers are muted in completion and warned semantically.
  `[survey-date-completed]`, `[survey-time-completed]`,
  `[survey-date-started]`, `[survey-time-started]`, `[survey-duration]`, and
  `[survey-duration-completed]` query a target survey response by record,
  event, form, and optionally instance. They accept their cataloged event and
  instance qualifiers. Their first survey-instrument parameter is an explicit
  substitute for a missing current form, so form-less sources keep the family
  available with a prompt to name a known survey while known recordless or
  eventless sources mute and warn it. Missing response timestamps remain a
  runtime result rather than an authoring finding.
  `[form-url]` and `[form-link]` build a Data Entry URL from record, event,
  form, and optional instance. Both accept event and instance qualifiers and
  can select any known project form through their first parameter when no
  current form exists; `[form-link]` has a second free-text label parameter.
  They share the record/event and form-or-known-instrument contract, but use
  the generic `instrument` completion prompt because their target need not be
  a survey. The otherwise permissive first parameter of `[form-link]` remains
  undiagnosed only when a current form permits its runtime custom-label
  fallback; in a form-less source it cannot resolve a target and is warned.
  `[survey-url]` and `[survey-link]` consume the event, survey target, and
  optional instance from their shared runtime path. They can use a record or
  Twilio's public first-survey route; otherwise a form-less record-backed
  source must name a known survey. The public route keeps a bare URL/link
  active and limits explicit completion to its first survey. They and
  `[survey-access-code]` additionally support a separately cataloged bare
  survey-participant target: an explicit participant source can resolve the
  no-parameter, unqualified form from the participant's current survey. This
  does not relax the event/form/record checks for `[survey-url:survey_form]`, which
  redirects the target away from that participant.
  `[survey-link:custom text]` is a current-form label fallback, so it is
  warned when it cannot select a public target. `[survey-access-code]` derives
  its value through that same route. `[survey-return-code]` calls
  `Survey::getSurveyReturnCode()` and has no recordless fallback. Both codes
  consume the event and a survey target; the public route leaves a bare access
  code active while a return code is muted and warned for its missing record.
  Whether a response yields a code is runtime state, not an authoring error.
  `[is-survey]` and `[is-form]` are distinct PAGE-state booleans: neither
  accepts parameters or qualifiers, and neither consumes form metadata.
  `truthy_runtime_page` documents the page where each can be `1`; a source
  policy provides `piping_runtime_page` only for fixed routes. Survey Queue
  custom text runs on `surveys/index.php`, keeping `[is-survey]` active and
  warning/muting `[is-form]` as a fixed `0`; variable routes remain
  non-diagnostic.
  `[record-name]`, `[record-dag-id]`, `[record-dag-name]`, and
  `[record-dag-label]` are current-record values: their Piping cases ignore
  parameters and event/instance qualifiers, and the DAG variants look up the
  current record's assigned Data Access Group. Their catalog entries require a
  record context, so known recordless sources warn and mute them without
  treating an unassigned DAG as an authoring error.
  `[arm-number]`, `[arm-label]`, `[event-id]`, `[event-number]`,
  `[event-name]`, and `[event-label]` are likewise parameterless current-event
  values. Piping restores the incoming event ID per replacement, so prepended
  event and appended instance segments are accepted structurally but ignored;
  their catalog entries let completion and diagnostics surface that fact and
  mute them in known eventless sources.
  Bare `[previous-event-*]` and `[next-event-*]` values need an event context
  and resolve blank at a boundary, whereas bare `[first-event-*]` and
  `[last-event-*]` values retain Piping's intentional project-first-event
  fallback when no event is passed. All eight accept no parameters and ignore
  outer event/instance qualifiers. Their independent use as a dynamic event
  selector before another Piping reference remains governed by that following
  reference's qualifier capability.
  The bare standalone instance values—`[previous-instance]`,
  `[current-instance]`, `[next-instance]`, `[first-instance]`,
  `[last-instance]`, and `[new-instance]`—need a record and event plus either
  a form or a repeating event. Their cataloged
  `requires_form_or_repeating_event_context` property keeps that explicit
  alternative distinct from a normal form requirement and from structural
  `[field][*-instance]` syntax. The form-less Survey Queue policy receives the
  actual repeat status of the project first event, so completion and advisory
  diagnostics mute the variables only when neither context can resolve them.
  This remains an availability check: runtime output still depends on the
  actual repeat-instance list and, for `[new-instance]`, whether that form or
  event really repeats.
  `[user-name]`, `[user-fullname]`, `[user-email]`, `[user-dag-*]`, and
  `[user-role-*]` are parameterless values derived only from Piping's supplied
  user or its authenticated-request `USERID` fallback. They ignore
  event/instance qualifiers and resolve blank without either route, so known
 userless sources warn/mute them advisory-only; sources that may provide the
  fallback retain runtime compatibility.
  `[project-title]`, `[project-id]`, `[project-status]`, `[project-purpose]`,
  `[project-irb-number]`, `[redcap-base-url]`, `[redcap-version]`,
  `[redcap-version-url]`, and `[survey-base-url]` are parameterless global
  project/application values. They ignore event/instance qualifiers but remain
  available in any project-scoped authoring source, independent of record,
  event, form, and user context.
  `PipingFieldParameterCatalog` similarly owns the evidence-backed project-field
  modifier contract. Each catalog field carries its applicable
  `piping_parameters` for completion, and the top-level known modifier set lets
  the PHP/browser analyzers issue warning-only diagnostics for known modifiers
  used on an incompatible field. Its `exclusive_group` metadata also prevents
  conflicting known modifiers from being suggested together and warns if they
  are manually combined. Unknown modifiers remain runtime-compatible.
  `PipingSourcePolicyCatalog` separately records evidence-backed runtime
  contexts for named authoring sources. It drives the workspace's field-completion
  availability and both semantic analyzers: project-field references in those
  sources produce a warning, while only Smart Variables that explicitly
  require an unavailable record, event, form, user, public-survey, or
  repeating-event route are muted
  in completion and warned. Twilio's manual invitation path provides the first
project event and the public route for the project's `firstForm`, but no record or form;
  Project Dashboard rendering provides none of those contexts. The Twilio
  policy transports its project-specific public survey form, so completion and
  warnings reject another known survey. Unknown source kinds retain existing
  behavior. A source that evaluates once for each selected recipient may use
  `recipient_dependent_contexts` instead of incorrectly asserting a binary
  capability: the opener supplies each named context as `guaranteed`,
  `partial`, or `unavailable` through the browser and server semantic
  contexts. Partial remains advisory and preserves completion; unavailable
  retains the established restriction behavior.
  `[survey-queue-url]` and `[survey-queue-link]` additionally model the
  proven runtime alternative of a numeric survey participant ID, which their
  replacement resolves to a record before building the queue link. This is
  represented by `requires_record_or_survey_participant_context`; it is not a
  public-survey route. The capability is opt-in for known source policies—the
  bulk Survey Invitation composer is the first cataloged workspace source to
  declare `has_survey_participant_context: true`, after its recordless
  invitation branch was verified.
  The follow-up Survey Invitation dialog is opened from Data Entry and pipes
  both its subject and content before queueing. Its shared
  `survey_invitation_followup_email` policy therefore supplies the current
  record, event, selected survey form, and authenticated-request `USERID`
  fallback. It also records `Surveys/invite_participant_popup.php` as the
  actual evaluation route, so `[is-survey]` and `[is-form]` are correctly
  muted and warned as fixed `0` rather than inheriting the surrounding Data
  Entry page. The bulk invitation composer has a recipient-conditional policy:
  `email_participants.php` resolves text once per selected participant, using
  record, event, and form arguments only for record-backed recipients and a
  participant ID for recordless initial-survey recipients. Its source policy
  names those three `recipient_dependent_contexts`, while the opener derives
  their live `guaranteed`, `partial`, or `unavailable` state from the selected
  Participant List. Mixed selections retain completion but label and warn on
  direct record/event/form-dependent references; recordless selections hide
  field completion and receive the existing unavailable-context warnings.
  Their bare `[survey-url]`, `[survey-link]`, and `[survey-access-code]`
  references are the narrow exception: their verified participant fallback
  remains available, while explicit survey targets continue to warn.
  That state is supplied to both browser analysis and the server diagnostic
  fallback. The policy also records its guaranteed participant/user contexts
  and `Surveys/email_participants.php` evaluation route.
  The default Survey Queue custom-text renderer supplies a record and the
  project first event but no form. Its transported repeating-event state lets
  form-or-repeating-event Smart Variables remain available only when that
  exact first event repeats. Its language-specific translations remain outside
  that default-source policy: their separate, currently unenhanced
  Multi-Language rich-text surface pipes from the caller's `Context`, which is
  record-only for the standalone Queue, record/event/form on normal
  acknowledgements, and empty on the post-completion AJAX path. The default
  editor cannot distinguish those routes.
  Standard field-metadata editors deliberately have no policy that infers a
  record or delivery channel. `field_label` includes field labels, choice
  labels, and section headers; `field_note` is the ordinary Field Note. They
  are authored outside a record and evaluated later in the displayed record's
  context. The shared Data Entry/Survey renderer supplies record/event/
  instance/form values; public Survey pre-creation behavior, Twilio's
  channel-specific implementation, and blank PDFs must not restrict the
  editor. The openers identify the containing form for Field Embedding and now
  transport it as Piping `form_name`. The shared catalog exposes each form's
  `repeat_context` (`guaranteed`, `partial`, or `unavailable`) from its
  `Project::isRepeatingFormOrEvent()` status across designated events. This
  guides only the standalone runtime-blank `[previous-instance]`,
  `[current-instance]`, `[next-instance]`, and `[new-instance]` values: never
  repeating forms mute and warn, partially repeating forms retain them with an
  advisory. It is not record availability, a source policy, or a Twilio
  change. `[first-instance]` and `[last-instance]` retain their distinct
  non-repeating instance-1 runtime fallback. The field-reference qualifier
  audit intentionally leaves their shared-editor behavior unchanged:
  `pipeSpecialTags()` may normalize `previous`/`current`/`next` from the
  enclosing runtime form/event/instance before target-field resolution, and
  `replaceVariablesInLabel()` lets numeric suffixes on non-repeating targets
  resolve as ordinary field values. `first`/`last`/`new` take different
  target-form paths. The edited form profile is consequently not a safe rule
  for cross-form or event-qualified field references, and must not create
  record-based restrictions, generic completion changes, or a Twilio change.
  MyCap's editable participant display label is resolved centrally by
  `Participant::getParticipantIdentifier()`, which always supplies the
  participant's record and event but no form or survey participant to Piping.
  Its policy keeps record/event references available and warns/mutes only
  form-dependent Smart Variables. It intentionally leaves user and repeating
  context unclaimed because the resolver depends on its caller and Piping's
  `USERID` fallback for those values.
  Editable custom event labels have the same record/event but form-less
  contract. Their shared `DataEntry::getRecordCustomEventLabel()` renderer
  passes record, event, and instance without a form or participant; because it
  is called from multiple Data Entry routes, the policy deliberately omits
  fixed page, user, and repeating claims.
  The custom end-of-survey redirect runs through `Surveys/index.php` only
  after a response record exists. Both its public and participant branches
  pass the record, event, and current survey form to Piping, so its
  `survey_redirect_url` policy keeps field and record/event/form completion
  active, keeps `[is-survey]` active, and warns/mutes `[is-form]` as fixed
  `0`. The public branch has no participant ID; the policy consequently makes
  no participant, user, or repeating-context assertion.
  Survey confirmation-email subject and content use the shared
  `survey_confirmation_email` policy. Their common
  `Survey::sendSurveyConfirmationEmail()` call always supplies the completed
  record, event, instance, and configured survey form, but is invoked from
  regular/Twilio surveys, PROMIS, Data Entry, and the follow-up confirmation
  endpoint. It therefore declares record/event/form availability and no
  participant ID, while deliberately leaving user, repeating, public-survey,
  and PAGE-state context variable; page-state Smart Variables remain
  unlabelled rather than being diagnosed as fixed results.
  Survey acknowledgement Piping always runs through `Surveys/index.php` with
  the completed record and event, so its policy keeps those references active,
  keeps `[is-survey]` active, and warns/mutes `[is-form]` as fixed `0`.
  Standard web completion also passes the current form, but Twilio's
  acknowledgement call omits it. The policy deliberately leaves form,
  participant, user, public-survey, and repeating context variable instead of
  treating the Twilio-specific omission as a blanket restriction.
  The stop-action alternative acknowledgement is web-only: it receives the
  current event and form from `Surveys/index.php`, keeping form-dependent
  completion active, `[is-survey]` active, and `[is-form]` fixed at `0`.
  Twilio's stop-action flow sends the ordinary acknowledgement instead. The
  optional delete-response setting can remove the record before this
  alternative is piped, so its policy intentionally leaves record,
  participant, user, public-survey, and repeating context unclaimed rather
  than presenting a universal field-reference warning.
  Survey Instructions deliberately have no source policy. The web renderer
  passes event/form but can start a new public response without a record;
  Twilio either leaves the instructions unpiped until a record exists or pipes
  without a form argument. PDF output is a third renderer: it passes the form
  but blank PDFs have no record or event. Survey Settings cannot expose those
  runtime choices, and even a partial static policy would misdescribe literal
  Twilio text or blank-PDF output. The absent policy therefore preserves
  compatibility pending a context model that can faithfully distinguish the
  alternatives.
  Offline Instructions always call Piping on `Surveys/index.php` with the
  current event and form, so their policy keeps form-dependent completion
  active, `[is-survey]` active, and `[is-form]` fixed at `0`. A public offline
  page passes no record whereas a private participant may have one; the policy
  consequently leaves record, participant, user, public-survey, and repeating
  context variable, preserving field completion instead of imposing a
  public-only restriction.
  Automated Survey Invitation subject and content share the scheduler-time
  `survey_invitation_automated_email` policy. Before their queued invitation
  is persisted, `SurveyScheduler::scheduleParticipantInvitation()` pipes both
  with the target record, event, instance, selected form, and generated
  follow-up participant ID. Field and participant-aware completion is thus
  available and the route is not public; user, repeating, and PAGE-state
  context remain variable because scheduling can run interactively or in the
  background, so page-state Smart Variables remain unlabelled.
  Project Setup's Custom Record Label has the `custom_record_label` policy.
  `getCustomRecordLabels()` renders every displayed record with the first
  event in its arm, so it has record/event context and no participant or
  public-survey route. Data Entry and header paths can pre-resolve additional
  form, user, or repeat data while generic record lists do not; these and PAGE
  state consequently remain unclaimed, preserving compatible completion.
  Project Setup's Data Entry Trigger URL has the `data_entry_trigger_url`
  policy. `DataEntry::launchDataEntryTrigger()` pipes it after every record
  save with record/event context, including public surveys before their
  recordless guard applies. Normal form/survey saves pass an instrument but
  Data Comparison merges do not, so form, user, repeat, and PAGE state remain
  unclaimed; no survey participant or recordless public-survey route exists.
  e-Consent Custom Label has the `econsent_custom_label` policy. Its only
  renderer is the e-Consent PDF header, and
  `Econsent::getCustomEconsentLabel()` always supplies the current e-Consent
  instrument. Blank PDF generation supplies neither a record nor an event, so
  those contexts remain unclaimed while field completion stays compatible; the
  policy excludes survey-participant and public-survey fallbacks.
  Custom Repeating Instrument Label has the `repeating_instrument_label`
  policy. `RepeatInstance::getPipedCustomRepeatingFormLabels()` supplies the
  target record, event, repeating form, and exact instance. Whole repeating
  events never reach its Piping call, so form context is guaranteed and a
  repeating-event alternative is explicitly unavailable. Survey Queue and
  Participant List callers vary in user and page state, which remain
  unclaimed; no participant or public-survey fallback is supplied.
  Alerts & Notifications email subject, message, and SendGrid template data
  share the `alert_notification` policy. `Alerts::sendNotification()` pipes
  each with the alert record/event context. Conditional-logic-only alerts can
  have no instrument, and cron/interactive sends vary user, repeat, and page
  state, so those remain unclaimed. The policy excludes participant and
  public-survey fallback while retaining field completion.
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
  A separate, draft-aware PHP/browser semantic layer mirrors
  `doFieldEmbedding()` without changing runtime replacement: it flags an
  unknown field, record ID, other-form or other-page target, self/nested
  embed, and a second use of one field on the rendered form/survey page.
  The catalog records existing occurrences by metadata host and Choice Label
  code, so an update replaces its own stored occurrence but cannot silently
  reuse a field embedded by another host or another choice. Completion uses
  that same availability decision, and the server fallback scans the same
  ordinary HTML text-node scope.
- The authoring workspace opts into `rcDialog`'s `fullscreenToggle` control,
  whose native click and F2 handlers use bundled Font Awesome expand/compress
  icons. Fullscreen records the existing inline position and dimensions, pins
  the dialog at a 10-pixel viewport inset, and disables drag/resize; collapse
  restores the recorded geometry and controls. It no longer relies on jQuery
  UI icon CSS, widget behavior, or workspace-specific geometry CSS.
- Programmatic consumers use `ctx.setFullscreen(boolean)` after
  `dialog:shown` and `ctx.isFullscreen()` to share that exact behavior without
  exposing a title-bar button. The dynamic API deliberately rejects static
  `size: "fullscreen"` dialogs, which have no restorable collapsed geometry.
- rcDialog owns the fullscreen glyph's compact size and vertical alignment with
  its close control, so the workspace must not override those title-bar styles.
- The Field Annotation source textarea is read-only and click-only. Closing
  its editor restores focus to `#field_name`, rather than the source's old
  focus opener, so it cannot immediately reopen or become a direct text-entry
  surface while the workspace is visible.

### Deferred authoring UI/UX refinements

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
- Bulk Survey Invitation subject and content use the
  `survey_invitation_bulk_email` source policy. Record, event, and form
  availability is calculated from the selected recipients as guaranteed,
  partial, or unavailable, while a survey-participant context is guaranteed.
  In a recordless or mixed selection, bare `[instrument-name]`,
  `[instrument-label]`, and `[survey-title]` remain available because
  `Piping::pipeSpecialTags()` derives their current form from the participant
  ID. This is intentionally limited to bare, unqualified forms; it does not
  allow event/instance qualifiers, and an explicit `survey-title` instrument
  parameter uses its separately cataloged target route.
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
  label is likewise a form-aware one-line piping workspace in its existing
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
- Field embedding is now available from the explicit authoring actions for all
  currently supported Online Designer label hosts. Ordinary Section Header
  fields use the Field Label action; the Add/Edit Matrix dialog has a matching
  action for its shared Section Header. The Choice Editor has an action for
  the selected row's Choice Label and returns the saved text through its
  existing spreadsheet/update flow. Matrix headers retain their rich-text
  handoff. Choice Labels use the restricted `filter_tags` HTML mode and retain
  their established one-line `<br>` handling. Do not enable field embedding
  for generic piping-only surfaces such as survey instructions or exit text.
- Field Embedding now has a metadata-aware diagnostic and completion layer,
  separate from its pure parser. Its draft-aware catalog mirrors the runtime
  form/page, record-ID, self/nested, and one-use-per-rendered-page checks;
  the Field Label, Field Note, Matrix Section Header, and Choice Label editors
  pass their exact host metadata context. In particular, the shared Field
  Label control uses `sq_id` and `element_preceding_header` for a stand-alone
  section-header edit, since that legacy edit mode clears `field_name`; this
  preserves both cross-section placement checks and same-field label/header
  one-use diagnostics. The Online Designer refreshes the catalog after its
  incremental field-save render path as well as full-table reloads, so each
  later editor instance sees the current page boundaries and stored embeds;
  Field Embedding workspaces also request uncached catalog metadata when
  opened. Choice Labels identify their choice code so an embed in one choice
  remains reserved while another is edited.
  In rich-text sources, only malformed curly-brace syntax suppresses a Field
  Embedding semantic cascade; an unrelated Piping candidate split by markup
  cannot hide a duplicate embedding in a separate text node.

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

The editor separately gives an enabled `@IF` condition advisory Logic pass.
`Form::evaluateIfActionTag()` ultimately evaluates its raw condition through
the normal Logic runtime, so matching PHP/browser
`ActionTagConditionSemanticAnalyzer` products parse and semantically inspect
the parser-published condition text with the active editor catalog/context.
Their source-relative findings are shifted back into the annotation; they do
not evaluate the condition, alter branch selection, or analyze a disabled
`@.OFF.IF` container.

The Field Annotation editor also uses the parser's enabled condition spans to
route completion to the existing Logic completer. This supplies catalog-backed
Logic fields, events, smart variables, functions, and keywords in a recognized
enabled condition, rather than generic Action Tag suggestions. True/false
branches and explicitly disabled `@.OFF.IF` containers must not take that
route; completion remains non-evaluating and cannot choose a branch.

Enabled condition spans likewise delegate hover documentation to the shared
Logic field, smart-variable, and special-function renderers, preserving the
inner token's annotation-relative range for the hover anchor. A disabled
`@.OFF.IF` container, including nested conditions, receives no Logic hover;
this supplies metadata only and cannot evaluate or select a branch.

For enabled conditions, the editor keeps its structural condition marker and
layers standard Logic lexer markers for references, functions, literals,
keywords, and operators at the corresponding annotation offsets. Disabled
`@.OFF.IF` containers, including nested conditions, keep only structural
presentation. This lexical highlight layer is non-evaluating and cannot select
a branch.

The Field Annotation Summary renders Action Tag structure separately from an
`Enabled IF condition Logic` aggregate of Logic references and function calls.
It includes enabled nested conditions but excludes disabled `@.OFF.IF`
containers and descendants. This is a parser-derived description only: it
does not evaluate a condition or infer a selected branch.

Each enabled-condition Logic summary occurrence links to its exact source
range: selecting it activates Edit and selects the Logic token in ACE. The
separate Action Tag tree also links recognized tags and `@IF` container names,
including disabled entries, displayed parameters, and displayed condition text
to their exact ranges; malformed candidates remain plain structure content.
Source navigation does not evaluate a condition or select a branch.

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

**Deferred first post-initial-completion step:** settle an External Module
Action Tag extension contract before implementing it. A declarative
`config.json` schema may provide syntax/catalog properties, and a controlled
callback may be needed for module-provided catalog entries and/or parameter
validation. The contract must define those responsibilities, safe execution,
validation, and transport through `ExternalModules::getActionTags()` before
the enhanced editor relies on it. Until then, retain the current name and
description registration advisory only.

**Implemented initial scope:** `ActionTagSemanticAnalyzer` supplies a matching
PHP/browser advisory for an enabled, structurally valid tag name that is absent
from the project `action_tags` catalog. That catalog already merges
`Form::getActionTags()` with tags returned by
`ExternalModules::getActionTags($project_id)`, so it proves only core or
enabled-module registration for the current project. `ActionTagCatalog` adds
only separately traced built-in contracts. `@DEFAULT` has an equals sign and
nonempty single- or double-quoted value under `Form::getValueInQuotesActionTag()`;
`DataEntry` pipes that value and does not apply it to File Upload/Signature
fields. The catalog preserves a whitespace-only quoted value and quoted
JSON-looking text as runtime-accepted shapes, but warns for escaped delimiter
quotes that the simple runtime extractor cannot use. It does not infer other
field restrictions or inspect the quoted Piping content. `@PLACEHOLDER` shares
the quoted-value and escaped-delimiter contract, and `DataEntry` renders it
only for Text Box/Notes fields or the visible Auto Complete input of a
Drop-down/SQL field. A known non-Auto-Complete Drop-down or SQL field is
warned; absent validation context remains unrestrictive. `@SETVALUE` and its
deprecated legacy synonym `@PREFILL` use the same quoted-assignment extractor
and are likewise not applied to File Upload/Signature fields. The catalog gives
`@PREFILL` a name-only, warning-level deprecation advisory directing authors to
`@SETVALUE`, without changing its accepted syntax or applicability. When both
are present, the existing `DataEntry` runtime chooses `@SETVALUE`'s value; the
authoring layer preserves that collision precedence without creating a new
restriction. `@READONLY` explicitly accepts no parameter: its runtime checks
for the exact whitespace-delimited tag token, so assignments and parenthesized
arguments receive a warning rather than allowing whitespace-dependent legacy
spellings. `@READONLY-FORM` and `@READONLY-SURVEY` share that no-parameter
contract. The Form variant may apply to any instrument in Data Entry; the
Survey variant has an additional advisory when the selected form is known not
to be configured as a survey. Missing form context remains non-diagnostic.
`@HIDDEN`, `@HIDDEN-FORM`, and `@HIDDEN-SURVEY` use the same no-parameter
shape and form/survey split. The Survey variant receives the same
known-non-survey advisory; runtime conditional `@IF` resolution remains
unchanged, while enabled conditions receive separate advisory Logic diagnostics.
`@HIDDEN-PDF` also accepts no parameter. The PDF renderer applies it after
runtime `@IF` resolution, but the Field Annotation editor deliberately does
not infer a PDF-rendering context or availability warning.
`@HIDECHOICE` requires a nonempty quoted, comma-delimited choice-code list;
`DataEntry` resolves Piping in that list and consumes it only for Checkbox,
Radio, Drop-down/SQL, Yes-No, and True-False choice renderers. The advisory
warns for a known different field type, without attempting to validate possibly
dynamic choice codes or restrict the runtime's limited matrix behavior.
`@SHOWCHOICE` has the same quoted choice-list and choice-field contract. It
replaces any `@HIDECHOICE` result with all codes not explicitly shown, so the
runtime's documented precedence is retained without a synthetic conflict
warning. Its matrix behavior remains outside the catalog because the per-field
hide list and matrix-header rendering do not have matching support.
`@NONEOFTHEABOVE` requires a nonempty equals assignment on a Checkbox field.
Its runtime accepts an unquoted or quote-delimited comma-separated value,
filters the resulting codes against the field's choices, and does not Pipe the value.
The catalog does not add code-membership diagnostics; the existing structural
unquoted-parameter compatibility advisory remains independent.
`@RANDOMORDER` is recognized from its parsed tag name, so attached assignments
or parenthesized arguments leave randomization enabled and are ignored rather
than invalidating the tag. Its catalog gives that a warning-only ignored-value
diagnostic, then applies its concrete Checkbox, Radio, Drop-down/SQL, Yes-No,
and True-False target contract. All three applicable renderers skip shuffling
matrix fields. Field Annotation passes its live Matrix Group state to the
browser and fallback endpoint, which warns only for a known matrix field; a
missing context is deliberately non-restrictive.
`@MAXCHECKED` reads an equals-assignment through
`Form::getValueInActionTag()` only while rendering a Checkbox field, including
a Checkbox matrix, and sends its integer-cast value to the browser's checkbox
handlers. Only a result above zero limits selections. The catalog therefore
requires a nonempty canonical positive integer and warns about permissive
legacy values that truncate or yield no cap, while preserving quoted and
unquoted extractor forms. It deliberately does not infer record-page/survey
availability or data-import enforcement from Field Annotation.
`@MAXCHOICE` uses a parenthesized comma-delimited choice-code/limit map. Its
runtime retains only nonempty codes with nonnegative numeric limits, including
zero and fractional limits, then counts distinct saved values in the current
event. The catalog validates that exact map shape but does not Pipe it, validate
metadata code membership, or predict mutable project-wide counts. Checkbox,
Radio, Drop-down/SQL, Yes-No, and True-False renderers—including their supported
matrix variants—consume reached choices, and the server repeats the check on
save to protect against concurrent submissions.
`@MAXCHOICE-SURVEY-COMPLETE` reuses the same static map and target types, but
its runtime returns no limits for a form without a survey ID and counts only
completed survey responses through its participant/response join. Partial
survey responses and Data Entry entries do not count. When both variants are
active, rendering favors the survey-completion form. The catalog consequently
adds `requires_survey_form` while retaining the shared map checks; Field
Annotation warns only when the current form is known not to be a survey and
does not attempt count prediction, metadata choice validation, or a synthetic
tag-conflict warning.
`@NOMISSING` is a bare tag. Although the rendered-field matcher can recognize
its name before attached text, the `Form::hasActionTag()` consumers for labels,
exports, and import validation require the exact space-delimited token; its
catalog must therefore reject parameters rather than call them ignored. The
editor catalog carries the project’s parsed Missing Data Code availability and
warns only when that state is known false. It adds no field-type rule, because
runtime support is broader than any one renderer and configuration may later
be enabled.
`@NOW`, `@NOW-SERVER`, and `@NOW-UTC` are handled in browser-side
`enableActionTags()`: each recognized row receives a value only when its
literal `input[name]` is blank, and appended values or parenthesized arguments
are ignored. Catalog each tag with `parameter.kind: none` and
`ignores_parameter: true`, producing a warning rather than a rejection when
an author supplies one. The three sources remain distinct at runtime: local
browser time, the timestamp rendered with the page by the server, and browser
time converted to UTC, respectively. Do not infer a target field type,
validation, branching, import, or page-mode restriction: this consumer does
not examine metadata before it selects the input, while Field Annotation cannot
establish page lifecycle or import context. The contract also does not claim
that the tag makes a field read-only.
`@TODAY`, `@TODAY-SERVER`, and `@TODAY-UTC` follow that exact same browser-side
selection and parameter behavior. Their regular date branches use the local
browser date, page-render server date, and browser date converted to UTC,
respectively. Since the common time-validation path runs before those date
branches, documentation calling them date-only does not establish a valid
metadata restriction. Give them the same ignored-parameter-only definitions;
do not add target, validation, page-context, or synthetic `@NOW`/`@TODAY`
collision diagnostics.
`@USERNAME` is a server-rendered blank-value default. It uses the active user
(or impersonated user), with the survey session identity on a survey. Its
renderer identifies the tag name and ignores an attached value or argument, but
the enclosing form/survey path skips the project record-ID field exactly. Add
the ignored-parameter definition and `excluded_record_id_field`; the shared
catalog already provides `record_id_field`, while the workspace now forwards
the known target field to both browser and fallback analysis. Warn only for a
known record-ID target. Do not infer field type, validation, survey-user,
blank-value, pagination, or page-lifecycle restrictions, and do not state that
the tag makes the field read-only.
`@WORDLIMIT` and `@CHARLIMIT` share assignment parameters extracted by
`Form::getValueInActionTag()`, with or without wrapping quotes. `DataEntry`
uses each only when the extracted text is numeric and greater than zero, then
casts it to an integer before calling the browser counter. Use a shared
positive-numeric-assignment property, not a positive-integer property, so the
catalog does not reject fractional syntax that runtime accepts and truncates.
Both tags share the record-ID exclusion. The `if`/`elseif` ordering gives
`@CHARLIMIT` precedence, so add a cataloged suppression diagnostic only to
`@WORDLIMIT`. Do not infer a Text Box/Notes restriction: the helper selects
named inputs and textareas without field-metadata checks. Import and page
lifecycle are not statically knowable.
`@FORCE-MINMAX` is range enforcement, not a generic stricter validation mode.
The runtime recognizes its name and ignores an attached value or argument;
`DataEntry` uses it only while building a Text Box or Calculated Field's range
validation, where `redcap_validate()` blocks out-of-range values. `Records`
uses the same name-presence check to turn a configured minimum or maximum into
an import error. Field Annotation now supplies the live unsaved presence of a
minimum or maximum range to both browser and fallback analysis. The catalog
therefore warns only for a known non-Text-Box/non-Calculated-Field target or a
known absent range, and does not infer a validation subtype, record-ID,
page-lifecycle, participant, or broader import-context rule.
`@HIDEBUTTON` is a name-presence renderer behavior: an assignment or argument
is ignored, and `DataEntry` replaces the generated Now/Today control only for
a Text Box with an explicit date, time, or datetime validation. The catalog
lists those concrete validation names, including the legacy values normalized
during rendering, and warns only when the current unsaved field context proves
that no such control is generated. It deliberately does not infer the
project-wide control setting, page mode, or behavior from missing/stale field
metadata.
`@PASSWORDMASK` likewise ignores an attached assignment or argument, but the
runtime changes the input type only in the Text Box renderer. The renderer
skips or converts the record-ID field to hidden before that code runs. The
catalog therefore warns only for a known non-Text-Box target or known record
ID; validation type, browser autofill, and untraced action-tag interactions
remain outside the advisory contract.
`@RICHTEXT` also ignores an attached value, but requires a narrower conclusion
than its Notes-field help text suggests. The browser toolbar is initialized for
Notes fields only; the Piping and PDF paths additionally inspect raw
`@RICHTEXT` text while formatting Text Box or Notes values. The catalog thus
offers only the ignored-parameter advisory, with no Notes-only, record-ID,
toolbar-configuration, or control-availability diagnostic.
`@CONSENT-VERSION` ignores an attached value and sets the selected e-Consent
version only for a blank Text Box on a survey page. The shared form catalog now
marks surveys with an active e-Consent item through a read-only lookup. The
editor warns only for a known non-survey form, a known survey without active
e-Consent, the record-ID field, or a non-Text-Box target. It does not predict
the DAG/MLM-specific selected version, blank-value state, or survey submission.
`@INLINE-PREVIEW` ignores an attached value and applies only to a non-Signature
File Upload field or an attached Descriptive field. The field-context matcher
now supports explicitly excluded validations and a known required attachment,
using the existing shared `fields[].has_attachment` state without restricting a
missing/stale catalog. The renderer suppresses the preview toggle when
`@INLINE` is also active on a File Upload field; that collision is deliberately
scoped to File Upload fields because Descriptive fields have no corresponding
`@INLINE` renderer. Whether a value has been uploaded, its type can be
previewed, or a current page can show a control remains runtime-dependent.
`@INLINE` itself is a File Upload-only browser renderer, including Signature
fields. Its bare and empty-parenthesis forms use default sizing; assignments
are ignored. The optional comma-separated parenthesized values match
`DataEntry` exactly: each is PHP-numeric (including zero or negative values)
or a percentage in the range `(0, 100]`; one invalid value discards the whole
dimension list. The image renderer reads only width and height, so later valid
values receive an advisory. The catalog deliberately does not infer a current
file type/value or whether the PDF rendering path can use dimensions.
`@LATITUDE` and `@LONGITUDE` are name-only geolocation renderers. `DataEntry`
creates their button only in its Text Box path, and the browser helper writes
only to a text input, without requiring a numeric validation. The catalog
therefore warns only for a known non-Text-Box target and treats attached
assignments or arguments as ignored. If both names appear on a Text Box, the
PHP branch and browser startup logic choose latitude; `@LONGITUDE` receives a
scoped precedence advisory. Permission, position, blank-value, and save
outcomes remain runtime-dependent.
`@SAVE-PROMPT-EXEMPT` and `@SAVE-PROMPT-EXEMPT-WHEN-AUTOSET` are name-only,
field-local change-tracking controls. The first prevents that field's changes
from setting the page-wide leave-without-saving prompt flag; another changed
field still sets it. The second suppresses only an initial automatic assignment
to a blank field, including the server and browser auto-value paths; later
changes track normally. Neither tag consumes a parameter or has a field-type
restriction. Do not require an accompanying auto-set tag in static analysis,
because the runtime result depends on dynamic Piping and page/value state.
`@LANGUAGE-CURRENT-FORM` and `@LANGUAGE-CURRENT-SURVEY` ignore attached
parameters but are target-local Multi-Language Management controls. `DataEntry`
removes them unless the target is an unvalidated Text Box, Radio Button, or
Drop-down field. The browser applies the Form tag on Data Entry and the Survey
tag on survey pages, never on survey-response review pages. The project
catalog now exposes whether any active MLM language is enabled for each form's
Data Entry or Survey surface, so the editor warns only when that state is
known false; the Survey tag additionally warns on a known non-survey form.
It deliberately does not predict a user's language, matching choice code, or
paginated-survey placement.
`DesignController` reads this metadata through the namespaced
`MultiLanguageManagement\\MultiLanguage` class; this catalog transport does
not modify Multi-Language Management runtime behavior.
`@LANGUAGE-FORCE`, `@LANGUAGE-FORCE-FORM`, and
`@LANGUAGE-FORCE-SURVEY` require the documented nonempty quoted language-ID
assignment, which may be piped before the runtime checks whether it is active.
The unqualified variant can apply on either form surface, `-FORM` is limited to
Data Entry even for a survey-enabled instrument, and `-SURVEY` requires a
survey with an active Survey MLM language. The catalog's
`requires_any_multilanguage_contexts` property warns for the unqualified tag
only when every applicable surface is known inactive. It does not evaluate
Piping, determine the resulting language, or infer the last-tag-wins order
across all Data Entry fields/current survey-page fields.
`@LANGUAGE-SET`, `@LANGUAGE-SET-FORM`, and
`@LANGUAGE-SET-SURVEY` are no-parameter browser controls whose target field
value selects the displayed language. The renderer permits only Radio Button
and Drop-down fields. The base tag can apply on either MLM surface, `-FORM`
uses Data Entry even for a survey-enabled instrument, and `-SURVEY` needs a
survey with an active Survey language. The editor reuses the named MLM context
properties and warns only for known false state. It does not predict a current
choice value, its active-language status, cookie behavior, or ordering among
multiple tagged fields.
`@LANGUAGE-MENU-STATIC` is a no-parameter survey-page control that keeps the
language switcher visible. `MultiLanguage` checks its name only after applying
`@IF`, never applies it to PDFs, and needs at least two active Survey MLM
languages for the switcher to exist. The catalog adds a per-form
multiple-active-survey-language capability and warns only when it is known
false, after the existing survey-form gate. Page placement, conditional
outcome, PDF state, and rendered switcher state remain runtime-dependent.
The five `-APP` tags are REDCap Mobile App tags, not MyCap tags. Core registers
them through `Form::getActionTags()` only while the system Mobile App feature
is enabled, which means the existing project action-tag registry correctly
handles their availability without another catalog capability. The authoring
contracts require no parameter: `@APPUSERNAME-APP` and `@BARCODE-APP` apply to
Text Box/Notes fields, `@HIDDEN-APP` has no target restriction,
`@READONLY-APP` applies to editable mobile controls rather than Calculated or
Descriptive fields, and `@SYNC-APP` applies to File Upload/Signature fields.
The diagnostic remains advisory and does not attempt to model device state,
app identity, scanning, uploads, or rendering.
The first MyCap field-capture family is separately gated by MyCap's project
setting (or its system fallback) in `Form::getActionTags()`, so the existing
project action-tag registry again owns availability. `@MC-FIELD-FILE-IMAGECAPTURE`
is a bare File Upload-only tag. `@MC-FIELD-FILE-VIDEOCAPTURE` is File
Upload-only and accepts its bare default form or the runtime's optional,
unquoted `duration:audio-mute:flash-mode:device-position` settings; each
colon-delimited setting may be omitted, named settings are case-insensitive,
and omitted/invalid runtime settings fall back to defaults. The authoring
contract permits the supported partial forms but warns for syntax that cannot
produce the documented setting sequence. `@MC-FIELD-HIDDEN` is a bare,
any-field marker that excludes the field from MyCap. `@MC-FIELD-TEXT-BARCODE`
is likewise bare, but has no static field-type diagnostic: despite help's
Text Box/Notes guidance, the current generic MyCap annotation pass attaches
barcode settings after converting every non-skipped field. Do not model native
support, task/form enablement, capture output, or device permission state.
The four MyCap participant metadata tags—`@MC-PARTICIPANT-CODE`,
`@MC-PARTICIPANT-JOINDATE`, `@MC-PARTICIPANT-JOINDATE-UTC`, and
`@MC-PARTICIPANT-TIMEZONE`—are name-only markers. The existing Data Entry
matcher and the MyCap participant update helpers recognize the name but do not
consume an assignment or argument, so the catalog issues the existing ignored-
parameter advisory. The update loops skip a matched record-ID field, which is
the only static target restriction. The defaults created by the MyCap helpers
are Text Boxes (the two dates have datetime validation), but the runtime
selection and write paths have no target-type guard; do not turn help text into
a Text Box or validation diagnostic. Participant creation, joining, values,
and save outcomes remain runtime concerns.
The active-task result annotations use a deliberate, catalog-only supplement
to the user-facing Action Tag registry. MyCap creates fields with these
annotations and `ProjectHandler::processAnnotation()` maps 32 exact names to
provider metadata, but `Form::getActionTags()` does not expose them. When the
ordinary MyCap registration is present, the editor adds only that proven set
(the `AMS`, `AUD`, `FIT`, `REA`, `REC-AUD`, `RMO`, `SEL`, `SHO`, `SPR-AUDIO`,
`TIM`, and `TWO` families) to recognition and completion. It deliberately
omits the remaining `Annotation::TASK_ACTIVE_*` constants that lack a current
provider-info consumer. The tags are bare and require an enabled MyCap task
form. Because the conversion path applies provider metadata after any
supported/fallback field conversion and does not verify the active-task format,
there is no field-type or task-format diagnostic. Client processing, multiple-
annotation precedence, captured output, and uploads remain runtime concerns.
The seven required MyCap task-result tags—`@MC-TASK-UUID`,
`@MC-TASK-STARTDATE`, `@MC-TASK-ENDDATE`, `@MC-TASK-SCHEDULEDATE`,
`@MC-TASK-STATUS`, `@MC-TASK-SUPPLEMENTALDATA`, and
`@MC-TASK-SERIALIZEDRESULT`—are bare, exact annotations. The editor catalog
adds the read-only per-form `is_mycap_task` flag from the enabled MyCap task
rows because `ProjectMapper::save()` rejects a task whose `enabled_for_mycap`
flag is not `1`; diagnostics remain advisory and preserve compatibility when
that state is absent. `Task::getFormFields()` supplies canonical Text Box,
Drop-down, Notes, and File Upload fields, but `ProjectMapper::fieldMap()` does
not enforce types for the first six annotations, so no static type rule is
invented. `ResultHandler` uploads a serialized result only through a File
Upload field carrying `@MC-TASK-SERIALIZEDRESULT`; that tag alone receives the
File Upload diagnostic. Scheduling, result contents, participant state, and
transfer success remain runtime concerns.
`@DOWNLOAD-COUNT` extracts one parenthesized target with the shared Form
helper, removes brackets and literal spaces, and performs an exact project
metadata lookup. The authoring contract accepts the runtime's bare and
bracketed field-name forms and warns for an empty, malformed, unknown, or
non-downloadable target. The shared field catalog now exposes whether a
Descriptive field has an attachment, allowing a target to be verified as a
File Upload field or an attached Descriptive field. Do not infer a counter
field type restriction from help text: the browser incrementer uses any
matching named form control. Same-event/repeating-context behavior and whether
an actual download occurs remain runtime concerns.
`@CALCTEXT`/`@CALCDATE`
require a nonempty parenthesized Logic expression on a Text Box field, with a
date/datetime validation also required for `@CALCDATE`. The Field Annotation
workspace sends its unsaved type and validation to the fallback endpoint,
preserving browser/server parity while a field is edited. These are warning-only
because legacy metadata can save a nonfunctional form. No other parameter,
field applicability, context, or module-specific semantic is inferred; disabled
tags are omitted, and a missing catalog remains structural-only for stale clients.

The eventual validator therefore needs an extensible definition/schema mechanism. Until that exists, the EM may provide rich module-specific feedback on top of the shared parser. The parser API should not wait for this standardization.

## Current and Future Core Touchpoints

- `/home/gr/redcap/codebase/Classes/ActionTagParser.php` — implemented pure parser class.
- `/home/gr/redcap/codebase/Classes/AuthoringSyntax/` — implemented shared
  primitives, logic/piping syntax products, public Logic/Piping catalogs, and
  the deliberately narrow `ActionTagCatalog`.
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
   restricted authoring sources, including recipient-dependent three-state
   contexts when the concrete sender can derive them from its selected list.
   It intentionally leaves unknown parameters and broader source-specific
   smart-variable availability to runtime behavior until their contracts are
   cataloged.
8. **Completed (initial Action Tag semantic scope):** Add matching PHP/browser
   advisory diagnostics for a structurally valid, enabled Action Tag name that
   is not registered by REDCap or an enabled External Module in the current
   project. This consults the existing project catalog and, only for the
   runtime-traced built-in entries, cataloged `@DEFAULT` quoted-assignment and
   File Upload/Signature exclusion, `@PLACEHOLDER` quoted-value and
   field-type/Auto Complete contract, matching `@SETVALUE`/deprecated
   `@PREFILL` quoted-value and File Upload/Signature contracts (with a
   name-only replacement advisory for `@PREFILL`), an explicit no-parameter
   contract for the `@READONLY` family and a known-non-survey warning for
   `@READONLY-SURVEY`, matching no-parameter contracts and a known-non-survey
   warning for the `@HIDDEN` family, a no-parameter-only contract for
   `@HIDDEN-PDF`, and the quoted choice-list/choice-field contract for
   `@HIDECHOICE` and `@SHOWCHOICE`, the Checkbox-only assignment contract for
   `@NONEOFTHEABOVE`, and the choice-field/non-matrix, ignored-parameter
   contract for `@RANDOMORDER`, plus the Checkbox-only positive-integer
   assignment contract for `@MAXCHECKED`, plus the parenthesized choice-limit
   map and choice-field contract for `@MAXCHOICE`, plus its survey-only,
   completed-response variant `@MAXCHOICE-SURVEY-COMPLETE`, plus
   the bare, Missing-Data-Code-aware contract for `@NOMISSING`, plus
   the ignored-parameter contracts for `@NOW`, `@NOW-SERVER`, and `@NOW-UTC`,
   plus
   the matching ignored-parameter contracts for `@TODAY`, `@TODAY-SERVER`, and
   `@TODAY-UTC`, plus
   the record-ID-aware, ignored-parameter contract for `@USERNAME`, plus
   the record-ID-aware, positive-numeric and precedence-aware contracts for
   `@WORDLIMIT` and `@CHARLIMIT`, plus
   the ignored-parameter, Text Box/Calculated Field, and configured-range
   contract for `@FORCE-MINMAX`, plus
   the ignored-parameter, Text Box/date-time-validation contract for
   `@HIDEBUTTON`, plus
   the ignored-parameter, Text Box, and record-ID contract for
   `@PASSWORDMASK`, plus
   the ignored-parameter-only contract for `@RICHTEXT`, plus
   the ignored-parameter, active-e-Consent-survey, Text Box, and record-ID
   contract for `@CONSENT-VERSION`, plus
   the ignored-parameter, non-Signature-File-Upload/attached-Descriptive, and
   File-Upload-only `@INLINE` precedence contract for `@INLINE-PREVIEW`, plus
   the File-Upload-only, optional parenthesized-dimension contract for
   `@INLINE`, plus the ignored-parameter, Text-Box-only geolocation and
   Text-Box-scoped `@LATITUDE` precedence contract for `@LATITUDE` and
   `@LONGITUDE`, plus
   the ignored-parameter, field-local change-tracking contracts for
   `@SAVE-PROMPT-EXEMPT` and `@SAVE-PROMPT-EXEMPT-WHEN-AUTOSET`, plus
   the ignored-parameter, unvalidated-Text-Box/Radio/Drop-down, and
   active-Multi-Language-Management-surface contracts for
   `@LANGUAGE-CURRENT-FORM` and `@LANGUAGE-CURRENT-SURVEY` (with the latter's
   survey-only gate), plus
   the quoted-language-ID, active-Multi-Language-Management-surface contracts
   for `@LANGUAGE-FORCE`, `@LANGUAGE-FORCE-FORM`, and
   `@LANGUAGE-FORCE-SURVEY` (including their either-surface, Data-Entry-only,
   and survey-only variants), plus
   the ignored-parameter, Radio/Drop-down, and active-Multi-Language-
   Management-surface contracts for `@LANGUAGE-SET`, `@LANGUAGE-SET-FORM`, and
   `@LANGUAGE-SET-SURVEY` (including their either-surface, Data-Entry-only,
   and survey-only variants), plus
   the ignored-parameter, survey-only, multiple-active-Survey-MLM-language
   contract for `@LANGUAGE-MENU-STATIC`, plus
   the Mobile-App-enabled registration and no-parameter field contracts for
   `@APPUSERNAME-APP`, `@BARCODE-APP`, `@HIDDEN-APP`, `@READONLY-APP`, and
   `@SYNC-APP`, plus
   the MyCap-enabled registration and field-capture contracts for
   `@MC-FIELD-FILE-IMAGECAPTURE`, `@MC-FIELD-FILE-VIDEOCAPTURE`,
   `@MC-FIELD-HIDDEN`, and `@MC-FIELD-TEXT-BARCODE`, plus
   the MyCap participant-metadata, ignored-parameter, non-record-ID contracts
   for `@MC-PARTICIPANT-CODE`, `@MC-PARTICIPANT-JOINDATE`,
   `@MC-PARTICIPANT-JOINDATE-UTC`, and `@MC-PARTICIPANT-TIMEZONE`, plus
   the supplemental MyCap active-task recognition and bare/enabled-task-form
   contracts for the 32 provider-info `AMS`, `AUD`, `FIT`, `REA`, `REC-AUD`,
   `RMO`, `SEL`, `SHO`, `SPR-AUDIO`, `TIM`, and `TWO` tags, plus the 12
   current form-generator/result-mapper `HOL`, `PSA`, `SPA`,
   `SPR-TRANSCRIPTION`, `SPR-EDITED-TRANSCRIPTION`, `STR`, `TON`, `TOW`, and
   `TRA` tags, including their auto-generated/do-not-modify hover description,
   non-suggestible completion policy, and browser hover-routing/completion
   coverage, plus
   the MyCap task-result, enabled-task-form contracts for `@MC-TASK-UUID`,
   `@MC-TASK-STARTDATE`, `@MC-TASK-ENDDATE`, `@MC-TASK-SCHEDULEDATE`,
   `@MC-TASK-STATUS`, `@MC-TASK-SUPPLEMENTALDATA`, and
   `@MC-TASK-SERIALIZEDRESULT` (the latter File Upload-only), including their
   task-maintained non-suggestible completion policy, plus
   the parenthesized File Upload/attached-Descriptive target contract for
   `@DOWNLOAD-COUNT`, plus
   `@CALCTEXT`/`@CALCDATE` Logic and Text Box/date-validation contracts. It
   remains warning-only and leaves all other parameter, field-type, context,
   and module-specific semantics to a future schema-backed validator.
9. **Required before PR:** Follow and update the Manual,
   [`ADDING_AUTHORING_LANGUAGE_FEATURES.md`](ADDING_AUTHORING_LANGUAGE_FEATURES.md)
   for every new Smart Variable, Piping project-field modifier, Special
   Function, or Action Tag. It records the distinct runtime, parser, catalog,
   help, completion, diagnostic, and test responsibilities so authoring
   completion is not mistaken for runtime support.
10. Use the EM benchmark across representative projects to establish parser,
   resolver, preload, and evaluation costs before considering runtime use.
11. Publish `REDCap::parseActionTags()` only when its contract, PHPDoc, and
   compatibility policy are ready to be stable for developers.
12. Evaluate selective compatibility delegation in `ActionTags`, then migrate
   `Form`/runtime consumers one family at a time with regression coverage.
13. Introduce semantic tag definitions and a validator as a distinct
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
