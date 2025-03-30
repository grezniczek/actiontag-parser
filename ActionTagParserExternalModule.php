<?php namespace DE\RUB\ActionTagParserExternalModule;

use ExternalModules\AbstractExternalModule;

require_once "classes/ActionTagParser.php";
require_once "classes/ActionTagHelper.php";

use ActionTagParser\ActionTagParser;

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

        // Get all fields that have an action tag

        $fields = [];
        $Proj = new \Project($project_id);
        foreach ($Proj->metadata as $field_name => $field_metadata) {
            $misc = $field_metadata["misc"] ?? "";
            if (strpos($misc, "@") !== false) {
                $fields[$field_metadata["form_name"]."-".$field_metadata["field_order"]] = $field_metadata;
            }
        }
        ksort($fields);
        foreach ($fields as $_ => $field_metadata) {
            print "<hr><p class=\"ml-2\">Field: <b>{$field_metadata["field_name"]}</b></p><pre class=\"mr-2\">";
            $result = ActionTagParser::parse($field_metadata["misc"]);
            print_r($field_metadata["misc"]);
            print "<hr>";
            print_r($result);
            print "</pre>";
        }
    }

    function benchmark() {

        $n = max(intval($_GET["n"]), 1);
        $timings = [];

        $context = [
            "project_id" => $this->getProjectId(),
            // "record" => "1",
            // "instrument" => "form_1",
            // "event_id" => "118",
            // "instance" => "1",
        ];

        $filter = [];

        print "<h5>Timing ($n iterations)</h5>";
        print "<p>Set the number of iterations as GET parameter '<i>n</i>'.</p>";

        ActionTagParser::setCacheDisabled();
        for ($i = 0; $i < $n; $i++) {
            $start = microtime(true);
            $parser_tags = ActionTagParser::getActionTags($context, $filter);
            $end = microtime(true);
            $timings["Parser"][] = $end-$start;
        }
        ActionTagParser::setCacheEnabled();

        for ($i = 0; $i < $n; $i++) {
            $start = microtime(true);
            $helper_tags = ActionTagHelper::getActionTags($filter["tags"] ?? null, $filter["fields"] ?? null, $filter["instruments"] ?? null, null, $i);
            $end = microtime(true);
            $timings["Helper"][] = $end-$start;
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
                    print "<p class=\"ml-2\"><i>{$field["field"]}</i>";
                    if ($field["params"] != "") print " - <code>".htmlentities(str_replace("\n", " ", $field['params']))."</code>";
                    print "</p>";
                }
            }
        };

        print "<h5>Parser:</h5>";
        $print($parser_tags);
        print "<hr>";
        print "<h5>Helper:</h5>";
        $print_helper($helper_tags);
    }
}