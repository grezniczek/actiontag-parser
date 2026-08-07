# Steps to Take to Add a New Smart Variable, Special Function, or Action Tag

This is the required maintenance and PR checklist for author-facing REDCap
language features. Keep this document current whenever an implementation seam
or required verification step changes. A feature is not complete merely
because it appears in completion, help, or diagnostics: runtime behavior,
documentation, authoring support, and test coverage must agree.

When a feature consumes project metadata through the shared authoring catalog,
also identify its invalidation boundary. Online Designer metadata changes
reload the affected form and refetch that catalog; do not add a second client
side field cache without an equally complete add/rename/delete refresh plan.

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
| Smart variable | `Classes/Piping.php`: `Piping::getSpecialTagsInfo()` plus its replacement implementation | `Classes/AuthoringSyntax/Catalog/LogicSmartVariableCatalog.php` for Logic value kinds, source availability, and server-only dependency semantics; `Classes/AuthoringSyntax/Catalog/PipingSmartVariableCatalog.php` for Piping qualifier and evidence-backed parameter contracts; existing Smart Variables help is generated from `Piping` | `Controllers/DesignController.php`: `buildAuthoringSyntaxEditorCatalog()` | Piping replacement; Piping and Logic semantic diagnostics; PHP/JS parser fixtures if its grammar is new |
| Special Function | `Classes/LogicParser.php`: public runtime allowlist and implementation/translation | `Classes/AuthoringSyntax/Catalog/LogicFunctionCatalog.php`; `Design::renderSpecialFunctionInstructions()` renders its reference from that catalog | `Controllers/DesignController.php`: `functions` catalog | Runtime evaluation/translation; catalog completeness; PHP/JS semantic parity |
| Built-in Action Tag | `Classes/Form.php`: `Form::getActionTags()` plus every runtime consumer that implements the tag | `Design/action_tag_explain.php` and the catalog assembled from `Form::getActionTags()` | `Controllers/DesignController.php`: `action_tags` catalog | `ActionTagParser` PHP/JS fixtures; feature-specific runtime tests; Online Designer applicability |

External Module action tags are a separate case. Their manifests feed
`ExternalModules::getActionTags()` and are exposed by the project catalog and
Action Tags help automatically. The core must not add an EM-specific runtime
implementation or treat a module tag as a built-in tag.

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
   instrument targets, enumerated values, and unrestricted free text are
   distinct kinds. Unknown instrument values that may fall back to the current
   form must remain warning-only. Add PHP/browser semantic fixtures for every
   supported and explicitly unsupported form.
6. Confirm `buildAuthoringSyntaxEditorCatalog()` carries it to both browser
   analysis and the server fallback. This is automatic for values registered
   in `Piping::getSpecialTagsInfo()`: `PipingSemanticAnalyzer` will then
   recognize the base name, while `LogicSemanticAnalyzer` also consumes its
   typed or restricted Logic metadata. Add a test for either analyzer when
   changing its supported availability or semantics. Do not add unreviewed
   parameter or source-specific Piping diagnostics until their runtime
   behavior has a complete catalog contract.
7. Verify the Smart Variables reference dialog, completion, hover information,
   and every intentionally supported source policy. Do not enable field
   completion in a source that has no per-record replacement context.

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
   `Design/action_tag_explain.php` presents correct help. Include a
   feature-specific runtime test for field-type/context restrictions.
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
php UnitTests/vendor/bin/phpunit UnitTests/AuthoringSyntax/Piping/PipingSyntaxParserTest.php
node UnitTests/AuthoringSyntax/Piping/PipingSyntaxParserJsTest.js
php UnitTests/vendor/bin/phpunit UnitTests/AuthoringSyntax/Catalog/PipingSmartVariableCatalogTest.php
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
