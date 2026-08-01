<?php namespace DE\RUB\ActionTagParserExternalModule;

use ExternalModules\AbstractExternalModule;

require_once "classes/ActionTagParser_Old.php";
require_once "classes/ActionTagParser.php";
require_once "classes/ActionTagParserAdapter.php";
require_once "classes/ActionTagConditionResolver.php";
require_once "classes/ActionTagIndex.php";
require_once "classes/ActionTagDiagnosticLocations.php";
require_once "classes/ActionTagHelper.php";

use ActionTagParser\ActionTagParser as PureActionTagParser;
use ActionTagParser\ActionTagParser_Old;
use ActionTagParser\ActionTagConditionResolver;
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

    function explain() {
        $project_id = $this->getProjectId();
        $fields = [];
        $annotations = [];
        $Proj = new \Project($project_id);
        foreach ($Proj->metadata as $field_name => $field_metadata) {
            $misc = $field_metadata["misc"] ?? "";
            if (strpos($misc, "@") !== false) {
                $fields[$field_metadata["form_name"]."-".$field_metadata["field_order"]] = $field_metadata;
                $annotations[$field_name] = ['annotation' => $misc, 'instrument' => $field_metadata['form_name']];
            }
        }
        ksort($fields);

        $index = ActionTagIndex::build($annotations);
        $escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $get = static fn (string $key) => trim((string) ($_GET[$key] ?? ''));
        $record = $get('record');
        $eventId = $get('event_id');
        $instrument = $get('instrument');
        $resolverContext = ($record !== '' && $eventId !== '' && $instrument !== '') ? [
            'project_id' => $project_id,
            'record' => $record,
            'event_id' => $eventId,
            'instrument' => $instrument,
            'instance' => max(1, (int) ($get('instance') ?: 1)),
            'repeat_instrument' => $get('repeat_instrument'),
        ] : null;
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
        print '<div class="mb-3"><b>'.count($index['by_field']).'</b> fields, <b>'.count($index['by_tag']).'</b> distinct tags, <b>'.count($index['by_instrument']).'</b> instruments.</div>';
        print '<table class="table table-sm table-bordered" style="max-width:600px"><tr><th>Tag</th><th>Occurrences</th></tr>';
        foreach ($index['by_tag'] as $tag => $occurrences) print '<tr><td><code>'.$escape($tag).'</code></td><td>'.count($occurrences).'</td></tr>';
        print '</table>';
        print '<details class="mb-3"><summary>Resolve conditions in a runtime context</summary>';
        print '<form method="get" class="form-inline mt-2">';
        if (isset($_GET['pid'])) print '<input type="hidden" name="pid" value="'.$escape($_GET['pid']).'">';
        foreach (['record' => 'Record', 'event_id' => 'Event ID', 'instrument' => 'Instrument', 'instance' => 'Instance', 'repeat_instrument' => 'Repeat instrument'] as $key => $label) {
            print '<label class="mr-1">'.$escape($label).'</label><input class="form-control form-control-sm mr-2" name="'.$escape($key).'" value="'.$escape($get($key)).'">';
        }
        print '<button class="btn btn-sm btn-primary" type="submit">Resolve</button></form>';
        print '<p class="small text-muted mb-0">Record, event ID, and instrument are required. Resolution is an EM runtime helper; it is not part of the pure parser.</p></details>';

        foreach ($fields as $_ => $field_metadata) {
            $annotation = $field_metadata['misc'];
            $fast = PureActionTagParser::parse($annotation);
            $diagnostic = ActionTagDiagnosticLocations::enrich($annotation, PureActionTagParser::parse($annotation, ['mode' => 'diagnostic']));
            $resolved = null;
            $resolutionError = null;
            if ($resolverContext !== null) {
                try { $resolved = ActionTagConditionResolver::resolve($fast, $resolverContext); }
                catch (\Throwable $exception) { $resolutionError = $exception->getMessage(); }
            }
            print '<hr><h5>Field: <code>'.$escape($field_metadata['field_name']).'</code> <small class="text-muted">('.$escape($field_metadata['form_name']).')</small></h5>';
            print '<pre class="border p-2 bg-light">'.$escape($annotation).'</pre>';
            print '<h6>Fast tags</h6><table class="table table-sm"><tr><th>Tag</th><th>Parameter</th><th>Condition</th><th>Resolved</th></tr>';
            foreach ($fast['tags'] as $position => $tag) {
                $resolvedValue = $resolved === null ? '—' : ($resolved['tags'][$position]['active'] ? 'active' : 'inactive');
                print '<tr><td><code>'.$escape($tag['name']).'</code></td><td><code>'.$escape($tag['parameter']['raw'] ?? '').'</code></td><td>'.$renderConditions($tag, $fast['conditions']).'</td><td>'.$escape($resolvedValue).'</td></tr>';
            }
            print '</table>';
            if ($resolutionError !== null) print '<p class="text-danger">Condition resolution failed: '.$escape($resolutionError).'</p>';
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
    }

    function benchmark() {

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
