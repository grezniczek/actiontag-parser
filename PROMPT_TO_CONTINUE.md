# Continue: REDCap Authoring Syntax Diagnostics

Continue the Authoring Syntax Diagnostics work in the REDCap core checkout.
Treat this as an ongoing implementation effort, not a fresh design exercise.

## Workspace and collaboration conventions

- Core repository: `/home/gr/redcap/codebase`, branch
  `authoring-syntax-diagnostics`.
- Parser/external-module repository and living documentation:
  `/home/gr/redcap/dev-modules/actiontag_parser_v9.9.9`.
- The user normally makes commits. Do **not** stage or commit changes unless
  explicitly asked. End each coherent code slice with a concise suggested
  commit message.
- Preserve unrelated work in dirty trees. Use `rg` for searches and
  `apply_patch` for edits.
- If the user pastes a Windows screenshot, read it through its corresponding
  `/mnt/c/...` path.
- Keep these documents current whenever implementation or an architectural
  decision changes:
  - `REDCAP_CORE_INTEGRATION_PLAN.md`
  - `PARSER_REQUIREMENTS_AND_IMPLEMENTATION.md`

## Architecture that is now in place

The shared browser authoring workspace is at
`Resources/js/AuthoringSyntax/AuthoringWorkspace.js`. Call it with a stable
`ref`, plus only dynamic details such as `onSave`, `focusAfterClose`, and (when
needed) `fieldEmbeddingFormName`:

```js
openAuthoringWorkspace($(this), {
    ref: 'feature.source_name',
    focusAfterClose: '#a-safe-control',
    onSave: function(opener) { /* retain the source's existing behavior */ }
});
```

The static `AuthoringWorkspaceSourcePolicies` registry is authoritative for
syntax, kind, one-line/multi-line behavior, HTML mode, and field-embedding
permission. Do not duplicate those static flags at every call site. Parser
products remain context-free; project metadata/catalog data is an integration
layer rather than parser state.

For TinyMCE sources, invoke the documented bridge:

```js
REDCap.openTinyMCEAuthoringWorkspace($('#source-id'), {ref: 'feature.source_name'});
```

This uses a detached buffer and writes saved content back through TinyMCE, so
the hidden textarea and TinyMCE content cannot diverge.

For non-rich-text, manually invoked sources, use `readonly` plus `onfocus` and
`onclick` entry points. Always set a safe `focusAfterClose` target so Escape
does not immediately reopen the workspace. For a logic source, preserve any
existing trim/activation/validation callback in `onSave`.

## Implemented coverage

The policy registry currently covers the following exact refs:

- Field editor: calculations, SQL highlighting, field annotation,
  quick-edit action tags, branching logic, field note, and field label.
- Survey Settings: auto-continue logic, end-survey redirect URL,
  confirmation-email subject/body, offline instructions, instructions,
  acknowledgement, and stop-action acknowledgement.
- Automated Survey Invitations: email subject/body and condition logic.
- Survey Queue condition logic.
- Form Display Logic conditions, including repeater-created controls.
- Record Status Dashboard filter logic.
- Data Export report-builder advanced logic.
- Alerts & Notifications: trigger logic, email subject, rich-text message,
  and new SendGrid dynamic-template data values.
- Data Quality rule logic: both existing inline rules and the new-rule input.
- Custom Record Label, Custom Event Label, and repeating-instrument custom
  labels.

Piping supports field references, smart variables, colon parameters, and
same-form field-embedding suggestions where embedding is allowed. Piping and
embedding diagnostics/highlighting are distinct. HTML-aware sources use ACE
HTML highlighting; TinyMCE remains the visual rich-text editor. Single-line
`filter_tags()` sources implement their documented space-or-`<br>` handling.

SQL fields are intentionally syntax-highlighting-only for now (MariaDB/MySQL
ACE mode); Summary and Structural Analysis tabs are hidden for SQL.

## Important UI/behavior decisions

- The authoring workspace help must include the normal Piping help action
  (`pipingExplanation()`) where Piping applies.
- Field embedding is deliberately limited to Field Label and Field Note, and
  field suggestions there are restricted to the same form.
- Error marks are solid red outlines with 0.5 opacity; piping/embedding colors
  align with their help buttons.
- Field Note has a pencil entry point, plus F2 and double-click; it is not
  opened on ordinary focus.
- Field Label plan: use an explicit “Edit with authoring editor” action even
  when TinyMCE is enabled. This also applies to future rich-text, piping-enabled
  surfaces such as survey instructions/exit text.
- Keep existing formatting/validation semantics. Do not add the workspace to
  recipient-address, phone-number, or similar specialized controls merely
  because their runtime happens to pipe values.

## Recommended next work

Continue with the remaining direct, source-specific logic editors before
attempting generic framework hooks. The most promising next slices are:

1. PDF Snapshot trigger logic (`Classes/PdfSnapshot.php`).
2. Randomization real-time logic (`Classes/Randomization.php`).
3. MyCap participant eligibility/condition logic (`Classes/MyCap/Participant.php`).

For each, first trace the UI entry point, page asset loading, server-side save
path, and current focus/validation behavior. Add a precise policy ref, not a
generic catch-all. Defer generic External Modules/Vue utilities until their
compatibility scope is explicitly decided.

After those logic surfaces, revisit remaining piping surfaces (for example,
survey invitation composition) only after mapping which fields truly support
piping and whether each one is rich text, plain text, or otherwise constrained.

## Verification expected per slice

At minimum run:

```bash
git diff --check
php -l <each changed PHP file>
node --check Resources/js/AuthoringSyntax/AuthoringWorkspace.js
node UnitTests/AuthoringSyntax/Piping/PipingAuthoringWorkspaceTest.js
node UnitTests/AuthoringSyntax/Piping/PipingSyntaxParserJsTest.js
node UnitTests/AuthoringSyntax/Piping/HtmlTextNodeScannerTest.js
node UnitTests/AuthoringSyntax/Piping/AceHtmlModeTest.js
```

Also run `node --check` on any changed standalone JavaScript file. Report what
was changed, what behavior was deliberately preserved, test results, and one
suggested concise commit message. Do not claim manual browser testing unless it
was actually performed by the user or through an available browser session.
