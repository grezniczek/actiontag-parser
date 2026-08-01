# REDCap Core Action Tag Parser Integration Plan

## Status

Planning document. No REDCap core code is changed by this plan.

The parser will first be implemented and tested in this External Module. That implementation is deliberately portable: once it satisfies the documented contract, it can move to REDCap core without a parser rewrite. Core integration and replacement of legacy consumers are separate, incremental work.

Parser behavior, output structure, `@IF` handling, diagnostics, performance requirements, and test cases are defined in the companion [parser requirements and implementation document](PARSER_REQUIREMENTS_AND_IMPLEMENTATION.md). That document is the source of truth for parser-specific decisions.

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

The initial core change should add, rather than replace, this developer method:

```php
REDCap::parseActionTags(string $annotation, array $options = []): array
```

The method should expose the parser's documented fast and diagnostic modes without adding project-specific semantics. Its PHPDoc must link to the stable result contract and state that syntax recognition does not establish whether a tag is known or valid for a project.

Core owns the parser class and public facade. The EM remains the initial reference implementation and the place to develop the first semantic Field Annotation checker. A subsequent core semantic-validation initiative may provide shared schemas/registration, but must not be required before the parser API is released.

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

`ActionTagFieldsParser` and `ActionTagFieldsConditionResolver` are the initial EM-facing shape for that scoped work. Both accept the same caller-selected field list and provide `Instrument` and `Project` convenience scopes. The resolver consumes the parser helper's output, so a paged survey can parse or resolve only its page fields, a field-level consumer can pass one field, and inspection tooling can deliberately request the whole project.

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

## Planned Core Touchpoints

- `/home/gr/redcap/codebase/Classes/ActionTagParser.php` — new pure parser class.
- `/home/gr/redcap/codebase/Classes/REDCap.php` — documented developer-facing facade.
- `/home/gr/redcap/codebase/Classes/ActionTags.php` — later, selective compatibility delegation.
- `/home/gr/redcap/codebase/Classes/Form.php` — later, gradual assessment and migration of legacy helpers; no initial replacement.
- `/home/gr/redcap/codebase/Design/online_designer_render_fields.php` — likely early diagnostic-mode integration candidate.

## Core Rollout Sequence

1. Finalize and test the portable parser in this EM, including its documented `@IF`, fast-mode, and diagnostic-mode contract.
2. Add the unchanged parser to core and expose `REDCap::parseActionTags()` with developer documentation.
3. Port the shared fixture suite and verify that the additive core change has no effect on existing runtime consumers.
4. Integrate diagnostic output into a low-risk inspection-oriented core path.
5. Evaluate selective compatibility delegation in `ActionTags`.
6. Migrate legacy `Form`/runtime consumers gradually, retaining `Form::replaceIfActionTag()` as the runtime authority until a separately approved change replaces it.
7. Introduce semantic tag definitions and a validator as a distinct standardization project.

## Readiness for the Core Move

The EM parser is ready to move when it is demonstrably pure, has a stable fixture-tested public contract, has bounded failure behavior, and has predictable benchmark results. The detailed acceptance criteria are maintained in the parser requirements document.

At that point, the core change is a class relocation plus documented API integration. Compatibility migration and semantic standardization remain intentionally independent follow-on work.
