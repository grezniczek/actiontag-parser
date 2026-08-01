<?php namespace DE\RUB\ActionTagParserExternalModule;

use ExternalModules\AbstractExternalModule;

require_once "classes/ActionTagParser_Old.php";
require_once "classes/ActionTagParser.php";
require_once "classes/ActionTagParserAdapter.php";
require_once "classes/ActionTagConditionResolver.php";
require_once "classes/ActionTagProjectConditionResolver.php";
require_once "classes/ActionTagFieldsParser.php";
require_once "classes/ActionTagFieldsConditionResolver.php";
require_once "classes/ActionTagIndex.php";
require_once "classes/ActionTagDiagnosticLocations.php";
require_once "classes/ActionTagHelper.php";

use ActionTagParser\ActionTagParser as PureActionTagParser;
use ActionTagParser\ActionTagParser_Old;
use ActionTagParser\ActionTagConditionResolver;
use ActionTagParser\ActionTagProjectConditionResolver;
use ActionTagParser\ActionTagDiagnosticLocations;
use ActionTagParser\ActionTagIndex;

class ActionTagParserExternalModule extends AbstractExternalModule {


    function redcap_every_page_top($project_id = null) {
        if ($project_id == null) return;
    }

    function redcap_module_action_tag($action_tags, $project_id, $record, $instrument, $event_id, $group_id, $repeat_instance, $is_survey) {

        // Potential hook for action tags .. get's called from redcap_data_entry_form_top / redcap_survey_page_top only (we need full context)
        // Will only get called when one of the declared action tags is present on the given instrument.
        // @IF is taken care of
        
        /* $action_tags is an array
         *
         * Andy Martin syntax: Has issues with multiple same tags on a field
         *    [
         *       "@ACTION-TAG" => [
         *           "field_name" => [
         *               "params" => any parameters next to tag (supports string, list, or json)
         *           ]
         *       ]
         *    ]
         * 
         *  Better:
         *    [
         *        "@ACTION-TAG" => [
         *            [
         *                "field" => field_name,
         *                "params" => any parameters next to tag (supports string, list, or json),
         *                "on_page" => true|false (relevant for multi-page surveys; always true for data entry forms)
         *            ]
         *        ]
         *    ]
         */

    }

    /**
     * AJAX endpoints for the parser showcase. These deliberately keep runtime
     * record access and REDCap logic evaluation outside the pure parser.
     */
    function redcap_module_ajax($action, $payload, $project_id, $record, $instrument, $event_id, $repeat_instance, $survey_hash, $response_id, $survey_queue_hash, $page, $page_full, $user_id, $group_id) {
        if (empty($project_id)) {
            throw new \Exception('The resolver is available only in a project context.');
        }
        $payload = is_array($payload) ? $payload : [];
        // A project-link page has no record in its signed JSMO context. In that
        // case, use the authenticated user's DAG rather than treating it as an
        // unrestricted record query.
        global $user_rights;
        $dagId = $group_id;
        if (isset($user_rights['group_id']) && $user_rights['group_id'] !== '' && $user_rights['group_id'] !== null) {
            $dagId = $user_rights['group_id'];
        }

        if ($action === 'search-records') {
            $term = trim((string) ($payload['term'] ?? ''));
            $records = \Records::getRecordList($project_id, $dagId, false, false, null, 50, 0, [], false, $term);
            $results = [];
            foreach ($records ?: [] as $recordName) {
                $results[] = ['id' => (string) $recordName, 'text' => (string) $recordName];
            }
            return ['results' => $results];
        }

        if ($action === 'parse-annotation') {
            $annotation = (string) ($payload['annotation'] ?? '');
            $fast = PureActionTagParser::parse($annotation);
            $diagnostic = ActionTagDiagnosticLocations::enrich($annotation, PureActionTagParser::parse($annotation, ['mode' => 'diagnostic']));
            return ['fast' => $fast, 'diagnostic' => $diagnostic];
        }

        if ($action === 'run-benchmark') {
            return $this->runBenchmark($payload, $project_id, $dagId);
        }

        if ($action !== 'resolve-conditions') {
            throw new \Exception('Unsupported parser showcase action.');
        }

        $recordName = trim((string) ($payload['record'] ?? ''));
        $selectedEventId = trim((string) ($payload['event_id'] ?? ''));
        $selectedInstrument = trim((string) ($payload['instrument'] ?? ''));
        $selectedRepeatInstrument = trim((string) ($payload['repeat_instrument'] ?? ''));
        $selectedInstance = filter_var($payload['instance'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($recordName === '' || $selectedEventId === '' || $selectedInstrument === '' || $selectedInstance === false) {
            throw new \Exception('Record, event, instrument, and a positive instance are required.');
        }

        $Proj = new \Project($project_id);
        $metadata = $Proj->getMetadata();
        $forms = $Proj->getForms();
        $records = \Records::getRecordList($project_id, $dagId, false, false, null, null, 0, [$recordName]);
        $allowedRecords = array_map('strval', array_keys($records ?: []));
        if (!in_array($recordName, $allowedRecords, true)) {
            throw new \Exception('The selected record is not available in this project context.');
        }
        if (!isset($Proj->eventInfo[$selectedEventId])) {
            throw new \Exception('The selected event does not belong to this project.');
        }
        if (!isset($forms[$selectedInstrument])) {
            throw new \Exception('The selected instrument does not belong to this project.');
        }
        if ($Proj->longitudinal && !in_array($selectedInstrument, $Proj->eventsForms[$selectedEventId] ?? [], true)) {
            throw new \Exception('The selected instrument is not assigned to the selected event.');
        }
        if ($selectedRepeatInstrument !== '' && !isset($forms[$selectedRepeatInstrument])) {
            throw new \Exception('The selected repeat instrument does not belong to this project.');
        }

        $context = [
            'project_id' => $project_id,
            'record' => $recordName,
            'event_id' => $selectedEventId,
            'instrument' => $selectedInstrument,
            'instance' => $selectedInstance,
            'repeat_instrument' => $selectedRepeatInstrument,
        ];
        $parsedFields = [];
        foreach ($metadata as $fieldName => $fieldMetadata) {
            $annotation = $fieldMetadata['misc'] ?? '';
            if (strpos($annotation, '@') === false) continue;
            $parsedFields[$fieldName] = PureActionTagParser::parse($annotation);
        }
        $sandboxKey = '__action_tag_parser_sandbox__';
        if (array_key_exists('annotation', $payload)) {
            $parsedFields[$sandboxKey] = PureActionTagParser::parse((string) $payload['annotation']);
        }
        $resolvedFields = ActionTagProjectConditionResolver::resolveMany($parsedFields, $context);

        $fields = [];
        foreach ($resolvedFields as $fieldName => $resolved) {
            if ($fieldName === $sandboxKey) continue;
            foreach ($resolved['tags'] as $position => $tag) {
                $fields[$fieldName][$position] = [
                    'active' => (bool) $tag['active'],
                    'conditions_match' => (bool) $tag['conditions_match'],
                ];
            }
        }

        $sandbox = null;
        if (isset($resolvedFields[$sandboxKey])) {
            $sandbox = [];
            foreach ($resolvedFields[$sandboxKey]['tags'] as $position => $tag) {
                $sandbox[$position] = [
                    'active' => (bool) $tag['active'],
                    'conditions_match' => (bool) $tag['conditions_match'],
                ];
            }
        }

        return ['fields' => $fields, 'sandbox' => $sandbox];
    }

    /** @return array{iterations:int,annotated_fields:int,methods:array<string,array>} */
    private function runBenchmark(array $payload, int $projectId, $dagId): array {
        $iterations = filter_var($payload['iterations'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($iterations === false) throw new \Exception('Iterations must be a whole number from 1 to 100.');

        $recordName = trim((string) ($payload['record'] ?? ''));
        $selectedEventId = trim((string) ($payload['event_id'] ?? ''));
        $selectedInstrument = trim((string) ($payload['instrument'] ?? ''));
        $selectedRepeatInstrument = trim((string) ($payload['repeat_instrument'] ?? ''));
        $selectedInstance = filter_var($payload['instance'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($recordName === '' || $selectedEventId === '' || $selectedInstrument === '' || $selectedInstance === false) {
            throw new \Exception('Record, event, instrument, and a positive instance are required for the resolving benchmark.');
        }

        $Proj = new \Project($projectId);
        $metadata = $Proj->getMetadata();
        $forms = $Proj->getForms();
        $records = \Records::getRecordList($projectId, $dagId, false, false, null, null, 0, [$recordName]);
        if (!in_array($recordName, array_map('strval', array_keys($records ?: [])), true)) {
            throw new \Exception('The selected record is not available in this project context.');
        }
        if (!isset($Proj->eventInfo[$selectedEventId])) throw new \Exception('The selected event does not belong to this project.');
        if (!isset($forms[$selectedInstrument])) throw new \Exception('The selected instrument does not belong to this project.');
        if ($Proj->longitudinal && !in_array($selectedInstrument, $Proj->eventsForms[$selectedEventId] ?? [], true)) {
            throw new \Exception('The selected instrument is not assigned to the selected event.');
        }
        if ($selectedRepeatInstrument !== '' && !isset($forms[$selectedRepeatInstrument])) {
            throw new \Exception('The selected repeat instrument does not belong to this project.');
        }

        $annotations = [];
        foreach ($metadata as $fieldMetadata) {
            $annotation = $fieldMetadata['misc'] ?? '';
            if (strpos($annotation, '@') !== false) $annotations[] = $annotation;
        }
        $context = [
            'project_id' => $projectId,
            'record' => $recordName,
            'event_id' => $selectedEventId,
            'instrument' => $selectedInstrument,
            'instance' => $selectedInstance,
            'repeat_instrument' => $selectedRepeatInstrument,
        ];
        $parseOnlyContext = ['project_id' => $projectId];

        $methods = [];
        $methods['Pure parser (fast)'] = $this->benchmarkPair(
            static function () use ($annotations): void {
                foreach ($annotations as $annotation) PureActionTagParser::parse($annotation);
            },
            static function () use ($annotations, $context): void {
                $parsed = array_map(static fn (string $annotation): array => PureActionTagParser::parse($annotation), $annotations);
                ActionTagProjectConditionResolver::resolveMany($parsed, $context);
            },
            $iterations
        );
        $methods['Pure parser (diagnostic)'] = $this->benchmarkPair(
            static function () use ($annotations): void {
                foreach ($annotations as $annotation) PureActionTagParser::parse($annotation, ['mode' => 'diagnostic']);
            },
            static function () use ($annotations, $context): void {
                $parsed = array_map(static fn (string $annotation): array => PureActionTagParser::parse($annotation, ['mode' => 'diagnostic']), $annotations);
                ActionTagProjectConditionResolver::resolveMany($parsed, $context);
            },
            $iterations
        );
        $methods['Legacy parser'] = $this->benchmarkPair(
            static function () use ($parseOnlyContext): void {
                ActionTagParser_Old::setCacheDisabled();
                try { ActionTagParser_Old::getActionTags($parseOnlyContext); }
                finally { ActionTagParser_Old::setCacheEnabled(); }
            },
            static function () use ($context): void {
                ActionTagParser_Old::setCacheDisabled();
                try { ActionTagParser_Old::getActionTags($context); }
                finally { ActionTagParser_Old::setCacheEnabled(); }
            },
            $iterations
        );
        $methods['ActionTagHelper'] = $this->benchmarkPair(
            static function (): void {
                ActionTagHelper::$cache = [];
                ActionTagHelper::getActionTags();
            },
            static function () use ($context): void {
                ActionTagHelper::$cache = [];
                ActionTagHelper::getActionTags(null, null, null, $context);
            },
            $iterations
        );

        return ['iterations' => $iterations, 'annotated_fields' => count($annotations), 'methods' => $methods];
    }

    /** @return array{parse:array,resolve:array}|array{error:string} */
    private function benchmarkPair(callable $parse, callable $resolve, int $iterations): array {
        try {
            return [
                'parse' => $this->benchmarkSamples($parse, $iterations),
                'resolve' => $this->benchmarkSamples($resolve, $iterations),
            ];
        } catch (\Throwable $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    /** @return array{mean_us:float,stddev_us:float,min_us:float,max_us:float} */
    private function benchmarkSamples(callable $operation, int $iterations): array {
        $samples = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = hrtime(true);
            $operation();
            $samples[] = (hrtime(true) - $start) / 1000;
        }
        $mean = array_sum($samples) / count($samples);
        $variance = 0.0;
        foreach ($samples as $sample) $variance += ($sample - $mean) ** 2;
        return [
            'mean_us' => $mean,
            'stddev_us' => sqrt($variance / count($samples)),
            'min_us' => min($samples),
            'max_us' => max($samples),
        ];
    }

    function explain() {
        $project_id = $this->getProjectId();
        $fields = [];
        $annotations = [];
        $Proj = new \Project($project_id);
        $metadata = $Proj->getMetadata();
        $forms = $Proj->getForms();
        foreach ($metadata as $field_name => $field_metadata) {
            $misc = $field_metadata["misc"] ?? "";
            if (strpos($misc, "@") !== false) {
                $fields[$field_metadata["form_name"]."-".$field_metadata["field_order"]] = $field_metadata;
                $annotations[$field_name] = ['annotation' => $misc, 'instrument' => $field_metadata['form_name']];
            }
        }
        ksort($fields);

        $index = ActionTagIndex::build($annotations);
        $escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $eventOptions = [];
        foreach ($Proj->eventInfo as $id => $event) {
            $eventOptions[(string) $id] = $event['name_ext'] ?? $event['descrip'] ?? (string) $id;
        }
        $formOptions = [];
        foreach ($forms as $name => $form) {
            $formOptions[$name] = $form['menu'] ?? $name;
        }
        $eventForms = [];
        foreach ($Proj->eventInfo as $id => $_event) {
            $eventForms[(string) $id] = $Proj->eventsForms[$id] ?? array_keys($forms);
        }
        $renderConditions = static function (array $tag, array $conditions) use ($escape): string {
            $parts = [];
            foreach ($tag['conditional'] as $reference) {
                $raw = $conditions[$reference['id']]['raw'] ?? '(missing condition)';
                $parts[] = $reference['negated'] ? '!(' . $raw . ')' : '(' . $raw . ')';
            }
            return $parts === [] ? '' : '<code>' . $escape(implode(' AND ', $parts)) . '</code>';
        };
        $renderNodes = null;
        $renderNodes = static function (array $nodes, array $conditions) use (&$renderNodes, $escape, $renderConditions): void {
            print '<ul class="mb-1">';
            foreach ($nodes as $node) {
                if ($node['type'] === 'text') continue;
                if ($node['type'] === 'tag') {
                    print '<li><b>'.$escape($node['name']).'</b>';
                    if ($node['parameter'] !== null) print ' <code>'.$escape($node['parameter']['raw']).'</code>';
                    $conditional = $renderConditions($node, $conditions);
                    if ($conditional !== '') print ' <span class="text-muted">if</span> '.$conditional;
                    print '</li>';
                } elseif ($node['type'] === 'if') {
                    print '<li><b>@IF</b> <code>'.$escape($conditions[$node['condition_id']]['raw'] ?? '').'</code>';
                    print '<div class="ml-2 text-muted">then</div>';
                    $renderNodes($node['then'], $conditions);
                    print '<div class="ml-2 text-muted">else</div>';
                    $renderNodes($node['else'], $conditions);
                    print '</li>';
                } else {
                    print '<li><span class="text-warning">Candidate</span> <code>'.$escape($node['raw']).'</code></li>';
                }
            }
            print '</ul>';
        };

        print '<h5>Parser showcase</h5>';
        print '<p>Fast parsing provides flattened usable tags; diagnostic parsing retains structure and source findings. The benchmark page compares timing with the established helpers.</p>';
        print '<div class="card mb-3"><div class="card-body p-3"><h6 class="card-title">Try an annotation</h6>';
        print '<label class="sr-only" for="atp-annotation-input">Field annotation</label><textarea id="atp-annotation-input" class="form-control form-control-sm" rows="4" spellcheck="false" placeholder="@IF([age] &gt; 18, @READONLY, \"\")"></textarea>';
        print '<div class="mt-2"><button id="atp-parse-annotation" class="btn btn-sm btn-primary" type="button">Parse annotation</button><span id="atp-parse-status" class="small ml-2" aria-live="polite"></span></div>';
        print '<div id="atp-annotation-output" class="mt-3"></div></div></div>';
        print '<div class="mb-3"><b>'.count($index['by_field']).'</b> fields, <b>'.count($index['by_tag']).'</b> distinct tags, <b>'.count($index['by_instrument']).'</b> instruments.</div>';
        print '<table class="table table-sm table-bordered" style="max-width:600px"><tr><th>Tag</th><th>Occurrences</th></tr>';
        foreach ($index['by_tag'] as $tag => $occurrences) print '<tr><td><code>'.$escape($tag).'</code></td><td>'.count($occurrences).'</td></tr>';
        print '</table>';
        print '<details class="mb-3"><summary>Resolve conditions in a runtime context</summary>';
        print '<div class="form-row mt-2">';
        print '<div class="form-group col-md-3"><label for="atp-resolve-record">Record</label><select id="atp-resolve-record" class="form-control form-control-sm"><option value=""></option></select></div>';
        print '<div class="form-group col-md-3"><label for="atp-resolve-event">Event</label><select id="atp-resolve-event" class="form-control form-control-sm">';
        foreach ($eventOptions as $id => $label) print '<option value="'.$escape($id).'">'.$escape($label).'</option>';
        print '</select></div>';
        print '<div class="form-group col-md-3"><label for="atp-resolve-instrument">Instrument</label><select id="atp-resolve-instrument" class="form-control form-control-sm"></select></div>';
        print '<div class="form-group col-md-3"><label for="atp-resolve-repeat-instrument">Repeat instrument</label><select id="atp-resolve-repeat-instrument" class="form-control form-control-sm"></select></div>';
        print '<div class="form-group col-md-2"><label for="atp-resolve-instance">Instance</label><input id="atp-resolve-instance" class="form-control form-control-sm" type="number" min="1" value="1"></div>';
        print '<div class="form-group col-md-10 d-flex align-items-end"><button id="atp-resolve-button" class="btn btn-sm btn-primary" type="button">Resolve</button><span id="atp-resolve-status" class="small ml-2" aria-live="polite"></span></div>';
        print '</div><p class="small text-muted mb-0">Resolution is an EM runtime helper. It also covers the most recently parsed annotation above; the pure parser neither fetches records nor evaluates REDCap logic.</p></details>';

        foreach ($fields as $_ => $field_metadata) {
            $annotation = $field_metadata['misc'];
            $fast = PureActionTagParser::parse($annotation);
            $diagnostic = ActionTagDiagnosticLocations::enrich($annotation, PureActionTagParser::parse($annotation, ['mode' => 'diagnostic']));
            print '<hr><h5>Field: <code>'.$escape($field_metadata['field_name']).'</code> <small class="text-muted">('.$escape($field_metadata['form_name']).')</small></h5>';
            print '<pre class="border p-2 bg-light">'.$escape($annotation).'</pre>';
            print '<h6>Fast tags</h6><table class="table table-sm"><tr><th>Tag</th><th>Parameter</th><th>Condition</th><th>Resolved</th></tr>';
            foreach ($fast['tags'] as $position => $tag) {
                print '<tr><td><code>'.$escape($tag['name']).'</code></td><td><code>'.$escape($tag['parameter']['raw'] ?? '').'</code></td><td>'.$renderConditions($tag, $fast['conditions']).'</td><td data-resolve-state data-resolve-field="'.$escape($field_metadata['field_name']).'" data-resolve-tag-index="'.$position.'">—</td></tr>';
            }
            print '</table>';
            print '<details><summary>Diagnostic structure and findings ('.count($diagnostic['diagnostics']).')</summary>';
            $renderNodes($diagnostic['nodes'], $diagnostic['conditions']);
            if ($diagnostic['diagnostics'] !== []) {
                print '<table class="table table-sm"><tr><th>Code</th><th>Location</th><th>Message</th></tr>';
                foreach ($diagnostic['diagnostics'] as $finding) {
                    print '<tr><td><code>'.$escape($finding['code']).'</code></td><td>'.$finding['start_line'].':'.$finding['start_column'].'</td><td>'.$escape($finding['message']).'</td></tr>';
                }
                print '</table>';
            }
            print '</details>';
        }

        $this->initializeJavascriptModuleObject();
        $jsmo = $this->getJavascriptModuleObjectName();
        $javascriptOptions = json_encode(['forms' => $formOptions, 'eventForms' => $eventForms], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        print '<script>(function ($) {';
        print 'var JSMO = '.$jsmo.'; var options = '.$javascriptOptions.'; var parsedSandboxAnnotation = null;';
        print 'function escapeHtml(value) { return $("<div>").text(value === null || value === undefined ? "" : String(value)).html(); }';
        print 'function renderConditions(tag, conditions) { var parts = []; $.each(tag.conditional || [], function (_, reference) { var condition = (conditions || {})[reference.id] || {}; parts.push((reference.negated ? "!(" : "(") + (condition.raw || "(missing condition)") + ")"); }); return parts.length ? "<code>" + escapeHtml(parts.join(" AND ")) + "</code>" : ""; }';
        print 'function renderSandbox(result) {';
        print 'var fast = result.fast || {}, diagnostic = result.diagnostic || {}, html = "<h6>Parsed annotation</h6><table class=\"table table-sm\"><thead><tr><th>Tag</th><th>Parameter</th><th>Condition</th><th>Resolved</th></tr></thead><tbody>";';
        print '$.each(fast.tags || [], function (index, tag) { html += "<tr><td><code>" + escapeHtml(tag.name) + "</code></td><td><code>" + escapeHtml(tag.parameter ? tag.parameter.raw : "") + "</code></td><td>" + renderConditions(tag, fast.conditions) + "</td><td data-resolve-sandbox-tag-index=\"" + index + "\">—</td></tr>"; });';
        print 'html += (fast.tags || []).length ? "</tbody></table>" : "<tr><td colspan=\"4\" class=\"text-muted\">No action tags found.</td></tr></tbody></table>";';
        print 'if ((diagnostic.diagnostics || []).length) { html += "<details><summary>Diagnostic findings (" + diagnostic.diagnostics.length + ")</summary><table class=\"table table-sm\"><thead><tr><th>Code</th><th>Location</th><th>Message</th></tr></thead><tbody>"; $.each(diagnostic.diagnostics, function (_, finding) { html += "<tr><td><code>" + escapeHtml(finding.code) + "</code></td><td>" + escapeHtml((finding.start_line || "?") + ":" + (finding.start_column || "?")) + "</td><td>" + escapeHtml(finding.message) + "</td></tr>"; }); html += "</tbody></table></details>"; }';
        print '$("#atp-annotation-output").html(html);';
        print '}';
        print 'function populateInstruments() {';
        print 'var forms = options.eventForms[$("#atp-resolve-event").val()] || Object.keys(options.forms);';
        print 'var $instrument = $("#atp-resolve-instrument"), $repeat = $("#atp-resolve-repeat-instrument");';
        print '$instrument.empty(); $repeat.empty().append($("<option>", {value: "", text: "Not repeating"}));';
        print '$.each(forms, function (_, name) { var label = options.forms[name] || name; $instrument.append($("<option>", {value: name, text: label})); $repeat.append($("<option>", {value: name, text: label})); });';
        print '$instrument.trigger("change.select2"); $repeat.trigger("change.select2");';
        print '}';
        print '$(function () {';
        print 'populateInstruments();';
        print '$("#atp-resolve-record").select2({width: "100%", placeholder: "Search records", minimumInputLength: 0, ajax: {delay: 150, transport: function (params, success, failure) { JSMO.ajax("search-records", {term: (params.data && params.data.term) || ""}).then(success).catch(failure); return {abort: function () {}}; }}});';
        print '$("#atp-resolve-event, #atp-resolve-instrument, #atp-resolve-repeat-instrument").select2({width: "100%"});';
        print '$("#atp-resolve-event").on("change", populateInstruments);';
        print '$("#atp-annotation-input").on("input", function () { if (parsedSandboxAnnotation !== null && parsedSandboxAnnotation !== $(this).val()) { parsedSandboxAnnotation = null; $("#atp-annotation-output [data-resolve-sandbox-tag-index]").text("—"); $("#atp-parse-status").removeClass("text-danger text-success").text("Annotation changed; parse again."); } });';
        print '$("#atp-parse-annotation").on("click", function () {';
        print 'var $button = $(this), $status = $("#atp-parse-status"), annotation = $("#atp-annotation-input").val(); $status.removeClass("text-danger text-success").text("Parsing…"); $button.prop("disabled", true);';
        print 'JSMO.ajax("parse-annotation", {annotation: annotation}).then(function (result) { parsedSandboxAnnotation = annotation; renderSandbox(result); $status.addClass("text-success").text("Parsed."); }).catch(function (error) { $status.addClass("text-danger").text(error && error.message ? error.message : String(error)); }).finally(function () { $button.prop("disabled", false); });';
        print '});';
        print '$("#atp-resolve-button").on("click", function () {';
        print 'var $button = $(this), $status = $("#atp-resolve-status"); $status.removeClass("text-danger text-success").text("Resolving…"); $button.prop("disabled", true);';
        print 'var payload = {record: $("#atp-resolve-record").val(), event_id: $("#atp-resolve-event").val(), instrument: $("#atp-resolve-instrument").val(), instance: $("#atp-resolve-instance").val(), repeat_instrument: $("#atp-resolve-repeat-instrument").val()}; if (parsedSandboxAnnotation !== null) payload.annotation = parsedSandboxAnnotation; JSMO.ajax("resolve-conditions", payload).then(function (response) {';
        print '$("[data-resolve-state]").each(function () { var tag = ((response.fields || {})[$(this).data("resolve-field")] || [])[$(this).data("resolve-tag-index")]; $(this).text(tag ? (tag.active ? "active" : "inactive") : "—"); });';
        print '$("[data-resolve-sandbox-tag-index]").each(function () { var tag = (response.sandbox || [])[$(this).data("resolve-sandbox-tag-index")]; $(this).text(tag ? (tag.active ? "active" : "inactive") : "—"); });';
        print '$status.addClass("text-success").text("Resolved.");';
        print '}).catch(function (error) { $status.addClass("text-danger").text(error && error.message ? error.message : String(error)); }).finally(function () { $button.prop("disabled", false); });';
        print '});';
        print '});';
        print '})(jQuery);</script>';
    }

    function benchmark() {
        $projectId = $this->getProjectId();
        $Proj = new \Project($projectId);
        $forms = $Proj->getForms();
        $escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $events = [];
        foreach ($Proj->eventInfo as $eventId => $event) {
            $events[(string) $eventId] = $event['name_ext'] ?? $event['descrip'] ?? (string) $eventId;
        }
        $formOptions = [];
        foreach ($forms as $formName => $form) $formOptions[$formName] = $form['menu'] ?? $formName;
        $eventForms = [];
        foreach ($Proj->eventInfo as $eventId => $_event) {
            $eventForms[(string) $eventId] = $Proj->eventsForms[$eventId] ?? array_keys($forms);
        }

        print '<h5>Action-tag benchmark</h5>';
        print '<p>Each run is one authenticated AJAX request. The timings are measured on the server and compare parsing alone with parsing plus runtime condition resolution.</p>';
        print '<div class="card mb-3"><div class="card-body p-3"><div class="form-row">';
        print '<div class="form-group col-md-2"><label for="atp-benchmark-iterations">Iterations</label><input id="atp-benchmark-iterations" class="form-control form-control-sm" type="number" min="1" max="100" value="10"><small class="form-text text-muted">1–100 per phase</small></div>';
        print '<div class="form-group col-md-3"><label for="atp-benchmark-record">Record</label><select id="atp-benchmark-record" class="form-control form-control-sm"><option value=""></option></select></div>';
        print '<div class="form-group col-md-3"><label for="atp-benchmark-event">Event</label><select id="atp-benchmark-event" class="form-control form-control-sm">';
        foreach ($events as $id => $label) print '<option value="'.$escape($id).'">'.$escape($label).'</option>';
        print '</select></div>';
        print '<div class="form-group col-md-2"><label for="atp-benchmark-instrument">Instrument</label><select id="atp-benchmark-instrument" class="form-control form-control-sm"></select></div>';
        print '<div class="form-group col-md-2"><label for="atp-benchmark-repeat-instrument">Repeat instrument</label><select id="atp-benchmark-repeat-instrument" class="form-control form-control-sm"></select></div>';
        print '<div class="form-group col-md-2"><label for="atp-benchmark-instance">Instance</label><input id="atp-benchmark-instance" class="form-control form-control-sm" type="number" min="1" value="1"></div>';
        print '<div class="form-group col-md-10 d-flex align-items-end"><button id="atp-run-benchmark" class="btn btn-sm btn-primary" type="button">Run benchmark</button><span id="atp-benchmark-status" class="small ml-2" aria-live="polite"></span></div>';
        print '</div><p class="small text-muted mb-0">The resolving columns use the selected record context. Comparator caches are cleared before each timed iteration.</p></div></div>';
        print '<div id="atp-benchmark-results"></div>';

        $this->initializeJavascriptModuleObject();
        $jsmo = $this->getJavascriptModuleObjectName();
        $options = json_encode(['forms' => $formOptions, 'eventForms' => $eventForms], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        print '<script>(function ($) {';
        print 'var JSMO = '.$jsmo.', options = '.$options.';';
        print 'function escapeHtml(value) { return $("<div>").text(value === null || value === undefined ? "" : String(value)).html(); }';
        print 'function populateInstruments() { var names = options.eventForms[$("#atp-benchmark-event").val()] || Object.keys(options.forms), $instrument = $("#atp-benchmark-instrument"), $repeat = $("#atp-benchmark-repeat-instrument"); $instrument.empty(); $repeat.empty().append($("<option>", {value: "", text: "Not repeating"})); $.each(names, function (_, name) { var label = options.forms[name] || name; $instrument.append($("<option>", {value: name, text: label})); $repeat.append($("<option>", {value: name, text: label})); }); $instrument.trigger("change.select2"); $repeat.trigger("change.select2"); }';
        print 'function stats(stats) { return escapeHtml(Number(stats.mean_us).toFixed(1) + " ± " + Number(stats.stddev_us).toFixed(1) + " µs"); }';
        print 'function renderResults(result) { var html = "<h6>Results</h6><p class=\"small text-muted\">" + escapeHtml(result.annotated_fields) + " annotated fields; " + escapeHtml(result.iterations) + " iterations per phase.</p><table class=\"table table-sm table-bordered\"><thead><tr><th>Method</th><th>Parse only — mean ± SD</th><th>Parse + resolve — mean ± SD</th></tr></thead><tbody>"; $.each(result.methods || {}, function (name, values) { if (values.error) html += "<tr><th>" + escapeHtml(name) + "</th><td colspan=\"2\" class=\"text-danger\">" + escapeHtml(values.error) + "</td></tr>"; else html += "<tr><th>" + escapeHtml(name) + "</th><td>" + stats(values.parse) + "</td><td>" + stats(values.resolve) + "</td></tr>"; }); $("#atp-benchmark-results").html(html + "</tbody></table>"); }';
        print '$(function () { populateInstruments(); $("#atp-benchmark-record").select2({width: "100%", placeholder: "Search records", minimumInputLength: 0, ajax: {delay: 150, transport: function (params, success, failure) { JSMO.ajax("search-records", {term: (params.data && params.data.term) || ""}).then(success).catch(failure); return {abort: function () {}}; }}}); $("#atp-benchmark-event, #atp-benchmark-instrument, #atp-benchmark-repeat-instrument").select2({width: "100%"}); $("#atp-benchmark-event").on("change", populateInstruments);';
        print '$("#atp-run-benchmark").on("click", function () { var $button = $(this), $status = $("#atp-benchmark-status"); $status.removeClass("text-danger text-success").text("Running…"); $button.prop("disabled", true); JSMO.ajax("run-benchmark", {iterations: $("#atp-benchmark-iterations").val(), record: $("#atp-benchmark-record").val(), event_id: $("#atp-benchmark-event").val(), instrument: $("#atp-benchmark-instrument").val(), instance: $("#atp-benchmark-instance").val(), repeat_instrument: $("#atp-benchmark-repeat-instrument").val()}).then(function (result) { renderResults(result); $status.addClass("text-success").text("Complete."); }).catch(function (error) { $status.addClass("text-danger").text(error && error.message ? error.message : String(error)); }).finally(function () { $button.prop("disabled", false); }); }); });';
        print '})(jQuery);</script>';
    }

    /** @deprecated Replaced by the AJAX benchmark above; retained temporarily for reference. */
    private function benchmarkLegacy() {

        $n = max(intval($_GET["n"]), 1);
        $timings = [];

        $context = [
            "project_id" => $this->getProjectId(),
            "record" => "2",
            "instrument" => "form_1",
            "event_id" => "1267",
            "instance" => "1",
        ];

        $filter = [];
        $annotations = [];
        $Proj = new \Project($context['project_id']);
        foreach ($Proj->getMetadata() as $field => $metadata) {
            $annotation = $metadata['misc'] ?? '';
            if (strpos($annotation, '@') !== false) $annotations[$field] = $annotation;
        }

        print "<h5>Timing ($n iterations)</h5>";
        print "<p>Set the number of iterations as GET parameter '<i>n</i>'.</p>";

        for ($i = 0; $i < $n; $i++) {
            $start = microtime(true);
            $helper_tags = ActionTagHelper::getActionTags($filter["tags"] ?? null, $filter["fields"] ?? null, $filter["instruments"] ?? null, $context, $i);
            $end = microtime(true);
            $timings["Helper"][] = $end-$start;
        }

        for ($i = 0; $i < $n; $i++) {
            $start = microtime(true);
            $pureTagCount = 0;
            foreach ($annotations as $annotation) {
                $pureTagCount += count(PureActionTagParser::parse($annotation)['tags']);
            }
            $end = microtime(true);
            $timings["Pure parser (fast)"][] = $end-$start;
        }

        for ($i = 0; $i < $n; $i++) {
            $start = microtime(true);
            foreach ($annotations as $annotation) {
                PureActionTagParser::parse($annotation, ['mode' => 'diagnostic']);
            }
            $end = microtime(true);
            $timings["Pure parser (diagnostic)"][] = $end-$start;
        }

        ActionTagParser_Old::setCacheDisabled();
        for ($i = 0; $i < $n; $i++) {
            $start = microtime(true);
            $parser_int_tags = ActionTagParser_Old::getActionTags($context, $filter, true);
            $end = microtime(true);
            $timings["Parser (internal)"][] = $end-$start;

        }
        for ($i = 0; $i < $n; $i++) {
            $start = microtime(true);
            $parser_tags = ActionTagParser_Old::getActionTags($context, $filter);
            $end = microtime(true);
            $timings["Parser"][] = $end-$start;
        }
        ActionTagParser_Old::setCacheEnabled();
        
        $by_field = ActionTagParser_Old::getActionTagsByField($context, $filter, true);
        $pureTagsByField = [];
        foreach ($annotations as $field => $annotation) {
            $parsed = PureActionTagParser::parse($annotation);
            foreach ($parsed['tags'] as $tag) {
                $conditions = [];
                foreach ($tag['conditional'] as $reference) {
                    $condition = $parsed['conditions'][$reference['id']]['raw'];
                    $conditions[] = $reference['negated'] ? '!(' . $condition . ')' : '(' . $condition . ')';
                }
                $pureTagsByField[$field][] = [
                    'name' => $tag['name'],
                    'parameter' => $tag['parameter']['raw'] ?? '',
                    'condition' => implode(' AND ', $conditions),
                ];
            }
        }

        // Calculat averange and standard deviation
        $avg = function($arr) {
            return array_sum($arr) / count($arr);
        };
        $sd = function($arr) use ($avg) {
            $mean = $avg($arr);
            $sum = 0;
            foreach ($arr as $value) {
                $sum += pow($value - $mean, 2);
            }
            return sqrt($sum / count($arr));
        };

        print "<table class=\"table table-sm table-responsive\" style=\"width: 350px;\">";
        print "<tr><th>Method</th><th>Mean<br>(µs)</th><th>Std. Dev.<br>(µs)</th></tr>";
        foreach ($timings as $key => $value) {
            $mean = $avg($timings[$key]);
            $std = $sd($timings[$key]);
            print "<tr><td>$key</td><td>".round($mean * 1000,2)."</td><td>".round($std * 1000, 2)."</td></tr>";
        }
        print "</table>";

        $print_helper = function($tags) {
            foreach ($tags as $tag => $fields) {
                print "<p><b>$tag</b></p>";
                foreach ($fields as $field => $params) {
                    print "<p class=\"ml-2\"><i>$field</i>";
                    if ($params['params'] != "") print " - <code>".htmlentities(str_replace("\n", " ", $params['params']))."</code>";
                    print "</p>";
                }
            }
        };

        $print = function($tags) {
            foreach ($tags as $tag => $fields) {
                print "<p><b>$tag</b></p>";
                foreach ($fields as $field) {
                    $nested = ($field["nested"] ?? false) ? " (nested)" : "";
                    print "<p class=\"ml-2\"><i>{$field["field"]}$nested</i>";
                    if ($field["params"] != "") print " - <code>".htmlentities(str_replace("\n", " ", $field['params']))."</code>";
                    print "</p>";
                }
            }
        };
        $printPure = function($tagsByField) {
            foreach ($tagsByField as $field => $tags) {
                print "<p><i>".htmlspecialchars($field, ENT_QUOTES, 'UTF-8')."</i></p>";
                foreach ($tags as $tag) {
                    $name = htmlspecialchars($tag['name'], ENT_QUOTES, 'UTF-8');
                    print "<p class=\"ml-2\"><b>$name</b>";
                    if ($tag['parameter'] !== '') {
                        print " - <code>".htmlspecialchars(str_replace("\n", ' ', $tag['parameter']), ENT_QUOTES, 'UTF-8')."</code>";
                    }
                    if ($tag['condition'] !== '') {
                        print " <span class=\"text-muted\">if</span> <code>".htmlspecialchars(str_replace("\n", ' ', $tag['condition']), ENT_QUOTES, 'UTF-8')."</code>";
                    }
                    print "</p>";
                }
            }
        };

        print "<h5>Parser:</h5>";
        $print($parser_tags);
        print "<hr>";
        print "<h5>Pure parser (fast):</h5>";
        print "<p>Parsed <b>$pureTagCount</b> action tags across ".count($annotations)." annotated fields.</p>";
        $printPure($pureTagsByField);
        print "<hr>";
        print "<h5>Parser (internal):</h5>";
        $print($parser_int_tags);
        print "<hr>";
        print "<h5>Helper:</h5>";
        $print_helper($helper_tags);
    }
}
